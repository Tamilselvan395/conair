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
        $this->blog = new \App\Models\Blog($pdo);
        $this->basePath = $basePath;
    }

    // Dashboard
    public function dashboard($request, $response)
    {
        $published = $this->pdo->query("
            SELECT COUNT(*) FROM blogs WHERE status='published'
        ")->fetchColumn();

        $draft = $this->pdo->query("
            SELECT COUNT(*) FROM blogs WHERE status='draft'
        ")->fetchColumn();

        return $this->twig->render($response, 'admin/dashboard.twig', [
            'title' => 'Dashboard',
            'published_count' => $published,
            'draft_count' => $draft
        ]);
    }

    // Blog list
    public function blogList($request, $response)
    {
        $blogs = $this->pdo->query("
            SELECT * FROM blogs ORDER BY created_at DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->twig->render($response, 'admin/blog/index.twig', [
            'title' => 'Manage Blogs',
            'blogs' => $blogs
        ]);
    }

    // Create blog
    public function createBlog($request, $response)
    {
        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));

            $imageName = null;

            if (isset($uploadedFiles['featured_image'])) {
                $image = $uploadedFiles['featured_image'];

                if ($image->getError() === UPLOAD_ERR_OK) {
                    $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                    $imageName = uniqid() . '.' . $extension;
                    $image->moveTo(__DIR__ . '/../../public/uploads/' . $imageName);
                }
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO blogs 
                (title, slug, content, meta_title, meta_description, featured_image, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['title'],
                $slug,
                $data['content'],
                $data['meta_title'],
                $data['meta_description'],
                $imageName,
                $data['status']
            ]);

            return $response
                ->withHeader('Location', $this->basePath . '/admin/blog')
                ->withStatus(302);
        }

        return $this->twig->render($response, 'admin/blog/create.twig', [
            'title' => 'Create Blog'
        ]);
    }

    // Edit blog
    public function editBlog($request, $response, $args)
    {
        $id = $args['id'];

        $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$blog) {
            throw new HttpNotFoundException($request);
        }

        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));

            $imageName = $blog['featured_image'];

            if (isset($uploadedFiles['featured_image'])) {
                $image = $uploadedFiles['featured_image'];

                if ($image->getError() === UPLOAD_ERR_OK) {
                    $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                    $imageName = uniqid() . '.' . $extension;
                    $image->moveTo(__DIR__ . '/../../public/uploads/' . $imageName);
                }
            }

            $update = $this->pdo->prepare("
                UPDATE blogs SET
                    title = ?,
                    slug = ?,
                    content = ?,
                    meta_title = ?,
                    meta_description = ?,
                    featured_image = ?,
                    status = ?
                WHERE id = ?
            ");

            $update->execute([
                $data['title'],
                $slug,
                $data['content'],
                $data['meta_title'],
                $data['meta_description'],
                $imageName,
                $data['status'],
                $id
            ]);

            return $response
                ->withHeader('Location', $this->basePath . '/admin/blog')
                ->withStatus(302);
        }

        return $this->twig->render($response, 'admin/blog/edit.twig', [
            'title' => 'Edit Blog',
            'blog' => $blog
        ]);
    }

    // Delete blog
    public function deleteBlog($request, $response, $args)
    {
        $stmt = $this->pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$args['id']]);

        return $response
            ->withHeader('Location', $this->basePath . '/admin/blog')
            ->withStatus(302);
    }

    public function login($request, $response)
    {
        if ($request->getMethod() === 'POST') {

            $data = $request->getParsedBody();

            $username = $data['username'];
            $password = $data['password'];

            // Hardcoded admin (for now)
            $adminUser = 'admin';
            $adminPass = '$2y$10$s3WlEOD3iO1Zt.vsSc4ZdOCU5rrizpbPvflnIlkeDRhYs7YEyUgu2'; 
            // password = admin123

            if ($username === $adminUser && password_verify($password, $adminPass)) {

                session_regenerate_id(true);
                $_SESSION['admin'] = true;

                return $response
                    ->withHeader('Location', $this->basePath . '/admin')
                    ->withStatus(302);
            }

            return $this->twig->render($response, 'admin/login.twig', [
                'error' => 'Invalid credentials'
            ]);
        }

        return $this->twig->render($response, 'admin/login.twig');
    }

    public function logout($request, $response)
    {
        session_destroy();

        return $response
            ->withHeader('Location', $this->basePath . '/admin/login')
            ->withStatus(302);
    }

}
