<?php

namespace App\Controllers;

use App\Models\Blog;
use Slim\Exception\HttpNotFoundException;

class AdminController
{
    private $twig;
    private $pdo;
    private $blog;
    private $basePath;

    public function __construct($twig, $pdo, $basePath)
    {
        $this->twig = $twig;
        $this->pdo = $pdo;
        $this->blog = new Blog($pdo);
        $this->basePath = $basePath;
    }

    /* =========================
       Dashboard
    ========================== */
    public function dashboard($request, $response)
    {
        // Published count
        $published = $this->pdo->query(
            "SELECT COUNT(*) FROM blogs WHERE status='published'"
        )->fetchColumn();
        
        // Draft count
        $draft = $this->pdo->query(
            "SELECT COUNT(*) FROM blogs WHERE status='draft'"
        )->fetchColumn();
        
        // Total blogs count
        $total = $this->pdo->query(
            "SELECT COUNT(*) FROM blogs"
        )->fetchColumn();
        
        // This month count
        $monthly = $this->pdo->query(
            "SELECT COUNT(*) FROM blogs WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        )->fetchColumn();
        
        // Recent blogs (last 5)
        $recent = $this->pdo->query(
            "SELECT * FROM blogs ORDER BY created_at DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->twig->render($response, 'admin/dashboard.twig', [
            'title' => 'Dashboard',
            'published_count' => $published ?: 0,
            'draft_count' => $draft ?: 0,
            'total_count' => $total ?: 0,
            'monthly_count' => $monthly ?: 0,
            'recent_blogs' => $recent ?: [],
            'active' => 'dashboard',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Blog List
    ========================== */
    public function blogList($request, $response)
    {
        // Get total count for pagination
        $total = $this->pdo->query(
            "SELECT COUNT(*) FROM blogs"
        )->fetchColumn();
        
        // Get all blogs
        $blogs = $this->pdo->query(
            "SELECT * FROM blogs ORDER BY created_at DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $this->twig->render($response, 'admin/blog/index.twig', [
            'title' => 'Manage Blogs',
            'blogs' => $blogs,
            'total_count' => $total,
            'active' => 'blog',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Create Blog
    ========================== */
    public function createBlog($request, $response)
    {
        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody() ?? [];
            $uploadedFiles = $request->getUploadedFiles();

            $title = $data['title'] ?? '';
            $content = $data['content'] ?? '';
            $meta_title = $data['meta_title'] ?? '';
            $meta_description = $data['meta_description'] ?? '';
            $status = $data['status'] ?? 'draft';

            // Generate slug from title
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            
            // Check if slug exists and make it unique
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ?");
            $stmt->execute([$slug]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $slug = $slug . '-' . ($count + 1);
            }

            $imageName = null;

            // Handle image upload
            if (!empty($uploadedFiles['featured_image'])) {
                $image = $uploadedFiles['featured_image'];

                if ($image->getError() === UPLOAD_ERR_OK) {
                    $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                    $imageName = uniqid() . '.' . $extension;
                    $uploadDir = __DIR__ . '/../../public/uploads/';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $image->moveTo($uploadDir . $imageName);
                }
            }

            // Insert blog
            $stmt = $this->pdo->prepare("
                INSERT INTO blogs 
                (title, slug, content, meta_title, meta_description, featured_image, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $title,
                $slug,
                $content,
                $meta_title,
                $meta_description,
                $imageName,
                $status
            ]);

            // Set success message
            $_SESSION['flash_success'] = 'Blog created successfully!';

            return $response
                ->withHeader('Location', $this->basePath . '/admin/blog')
                ->withStatus(302);
        }

        return $this->twig->render($response, 'admin/blog/create.twig', [
            'title' => 'Create Blog',
            'active' => 'blog',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Edit Blog
    ========================== */
    public function editBlog($request, $response, $args)
    {
        $id = $args['id'];

        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$blog) {
            throw new HttpNotFoundException($request, 'Blog not found');
        }

        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody() ?? [];
            $uploadedFiles = $request->getUploadedFiles();

            $title = $data['title'] ?? '';
            $content = $data['content'] ?? '';
            $meta_title = $data['meta_title'] ?? '';
            $meta_description = $data['meta_description'] ?? '';
            $status = $data['status'] ?? 'draft';
            $remove_image = isset($data['remove_image']);

            // Generate slug from title
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            
            // Check if slug exists and not current blog
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $slug = $slug . '-' . ($count + 1);
            }

            $imageName = $blog['featured_image'];

            // Remove image if checked
            if ($remove_image && $imageName) {
                $oldFile = __DIR__ . '/../../public/uploads/' . $imageName;
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $imageName = null;
            }

            // Handle new image upload
            if (!empty($uploadedFiles['featured_image'])) {
                $image = $uploadedFiles['featured_image'];

                if ($image->getError() === UPLOAD_ERR_OK) {
                    // Delete old image
                    if ($imageName) {
                        $oldFile = __DIR__ . '/../../public/uploads/' . $imageName;
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                    $imageName = uniqid() . '.' . $extension;
                    $uploadDir = __DIR__ . '/../../public/uploads/';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $image->moveTo($uploadDir . $imageName);
                }
            }

            // Update blog
            $update = $this->pdo->prepare("
                UPDATE blogs SET
                    title = ?,
                    slug = ?,
                    content = ?,
                    meta_title = ?,
                    meta_description = ?,
                    featured_image = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $update->execute([
                $title,
                $slug,
                $content,
                $meta_title,
                $meta_description,
                $imageName,
                $status,
                $id
            ]);

            $_SESSION['flash_success'] = 'Blog updated successfully!';

            return $response
                ->withHeader('Location', $this->basePath . '/admin/blog')
                ->withStatus(302);
        }

        return $this->twig->render($response, 'admin/blog/edit.twig', [
            'title' => 'Edit Blog',
            'blog' => $blog,
            'active' => 'blog',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Delete Blog
    ========================== */
    public function deleteBlog($request, $response, $args)
    {
        $id = $args['id'];

        // Get blog to delete image
        $stmt = $this->pdo->prepare("SELECT featured_image FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($blog && $blog['featured_image']) {
            $oldFile = __DIR__ . '/../../public/uploads/' . $blog['featured_image'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        // Delete blog
        $stmt = $this->pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['flash_success'] = 'Blog deleted successfully!';

        return $response
            ->withHeader('Location', $this->basePath . '/admin/blog')
            ->withStatus(302);
    }

    /* =========================
       Contact List
    ========================== */
    public function contactList($request, $response)
    {
        // Get total count
        $total = $this->pdo->query(
            "SELECT COUNT(*) FROM contacts"
        )->fetchColumn();
        
        // Get all contacts
        $stmt = $this->pdo->query(
            "SELECT * FROM contacts ORDER BY created_at DESC"
        );
        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->twig->render($response, 'admin/contacts/index.twig', [
            'title' => 'Contact Submissions',
            'contacts' => $contacts,
            'total_count' => $total,
            'active' => 'contacts',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Delete Contact
    ========================== */
    public function deleteContact($request, $response, $args)
    {
        $id = $args['id'];

        $stmt = $this->pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['flash_success'] = 'Contact deleted successfully!';

        return $response
            ->withHeader('Location', $this->basePath . '/admin/contacts')
            ->withStatus(302);
    }

    /* =========================
       Login
    ========================== */
    public function login($request, $response)
    {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
            return $response
                ->withHeader('Location', $this->basePath . '/admin')
                ->withStatus(302);
        }

        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody() ?? [];

            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            // Admin credentials
            $adminUser = 'admin';
            $adminPass = '$2y$10$s3WlEOD3iO1Zt.vsSc4ZdOCU5rrizpbPvflnIlkeDRhYs7YEyUgu2'; 
            // password = admin123

            if ($username === $adminUser && password_verify($password, $adminPass)) {

                session_regenerate_id(true);
                $_SESSION['admin'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

                return $response
                    ->withHeader('Location', $this->basePath . '/admin')
                    ->withStatus(302);
            }

            return $this->twig->render($response, 'admin/login.twig', [
                'title' => 'Admin Login',
                'error' => 'Invalid username or password'
            ]);
        }

        return $this->twig->render($response, 'admin/login.twig', [
            'title' => 'Admin Login'
        ]);
    }

    /* =========================
       Logout
    ========================== */
    public function logout($request, $response)
    {
        // Clear all session data
        $_SESSION = [];
        
        // Destroy the session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();

        return $response
            ->withHeader('Location', $this->basePath . '/admin/login')
            ->withStatus(302);
    }

    /* =========================
       Forgot Password (Optional)
    ========================== */
    public function forgotPassword($request, $response)
    {
        return $this->twig->render($response, 'admin/forgot-password.twig', [
            'title' => 'Forgot Password',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       Profile (Optional)
    ========================== */
    public function profile($request, $response)
    {
        return $this->twig->render($response, 'admin/profile.twig', [
            'title' => 'Admin Profile',
            'active' => 'profile',
            'base_url' => $this->basePath
        ]);
    }
}