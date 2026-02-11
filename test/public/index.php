<?php

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=conair", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Base path
// $app->setBasePath('/ongoing/conair/public');
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$app->setBasePath($basePath);

// Create Twig
$twig = Twig::create(__DIR__ . '/../templates', [
    'cache' => false
]);

// Auto detect scheme (http/https)
// $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// $host = $_SERVER['HTTP_HOST'];
// $basePath = $app->getBasePath();

// Global base_url for Twig
// $twig->getEnvironment()->addGlobal('base_url', $scheme . '://' . $host . $basePath);
$twig->getEnvironment()->addGlobal('base_url', $app->getBasePath());

// Add Twig Middleware
$app->add(TwigMiddleware::create($app, $twig));

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);


// $app->add(function ($request, $handler) {
//     $uri = $request->getUri();
//     $path = $uri->getPath();

//     if ($path !== '/' && substr($path, -1) === '/') {
//         $newUri = $uri->withPath(rtrim($path, '/'));
//         return (new \Slim\Psr7\Response())
//             ->withHeader('Location', (string)$newUri)
//             ->withStatus(301);
//     }

//     return $handler->handle($request);
// });


// Home Route
$app->get('/', function ($request, $response) use ($twig, $pdo) {

    $stmt = $pdo->query("
        SELECT * FROM blogs
        WHERE status = 'published'
        ORDER BY created_at DESC
        LIMIT 3
    ");

    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $twig->render($response, 'home.twig', [
        'title' => 'Home',
        'blogs' => $blogs
    ]);
});

// About Route
$app->get('/about', function ($request, $response) use ($twig) {
    return $twig->render($response, 'about.twig', [
        'title' => 'About'
    ]);
});

$app->get('/blog', function ($request, $response) use ($twig, $pdo) {

    $stmt = $pdo->query("
        SELECT * FROM blogs 
        WHERE status = 'published'
        ORDER BY created_at DESC
    ");

    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $twig->render($response, 'blog.twig', [
        'title' => 'Blog',
        'blogs' => $blogs
    ]);
});

$app->get('/blog/{slug}', function ($request, $response, $args) use ($twig, $pdo) {

    // Get current blog
    $stmt = $pdo->prepare("
        SELECT * FROM blogs 
        WHERE slug = ? AND status = 'published'
    ");
    $stmt->execute([$args['slug']]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$blog) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    // Get latest 5 posts EXCLUDING current one
    $recentStmt = $pdo->prepare("
        SELECT * FROM blogs
        WHERE status = 'published'
        AND id != ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$blog['id']]);
    $recentPosts = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    return $twig->render($response, 'blog_detail.twig', [
        'title' => $blog['meta_title'],
        'meta_description' => $blog['meta_description'],
        'blog' => $blog,
        'recentPosts' => $recentPosts
    ]);
});

$app->get('/admin', function ($request, $response) use ($twig, $pdo) {

    $published = $pdo->query("
        SELECT COUNT(*) FROM blogs WHERE status='published'
    ")->fetchColumn();

    $draft = $pdo->query("
        SELECT COUNT(*) FROM blogs WHERE status='draft'
    ")->fetchColumn();

    return $twig->render($response, 'admin/dashboard.twig', [
        'title' => 'Dashboard',
        'published_count' => $published,
        'draft_count' => $draft
    ]);
});
$app->get('/admin/blog', function ($request, $response) use ($twig, $pdo) {

    $stmt = $pdo->query("
        SELECT * FROM blogs
        ORDER BY created_at DESC
    ");

    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $twig->render($response, 'admin/blog/index.twig', [
        'title' => 'Manage Blogs',
        'blogs' => $blogs
    ]);
});

$app->map(['GET', 'POST'], '/admin/blog/create', function ($request, $response) use ($twig, $pdo, $app) {

    if ($request->getMethod() === 'POST') {

        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));

        $imageName = null;

        // Handle image upload
        if (isset($uploadedFiles['featured_image'])) {
            $image = $uploadedFiles['featured_image'];

            if ($image->getError() === UPLOAD_ERR_OK) {
                $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                $imageName = uniqid() . '.' . $extension;
                $image->moveTo(__DIR__ . '/uploads/' . $imageName);
            }
        }

        $stmt = $pdo->prepare("
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
            ->withHeader('Location', $app->getBasePath() . '/admin/blog')
            ->withStatus(302);
    }

    return $twig->render($response, 'admin/blog/create.twig', [
        'title' => 'Create Blog'
    ]);
});


$app->map(['GET','POST'], '/admin/blog/edit/{id}', function ($request, $response, $args) use ($twig, $pdo, $app) {

    $id = $args['id'];

    // Get blog
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$blog) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if ($request->getMethod() === 'POST') {

        $data = $request->getParsedBody();

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));

        $update = $pdo->prepare("
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

        $uploadedFiles = $request->getUploadedFiles();
        $imageName = $blog['featured_image']; // keep old image

        if (isset($uploadedFiles['featured_image'])) {
            $image = $uploadedFiles['featured_image'];

            if ($image->getError() === UPLOAD_ERR_OK) {
                $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
                $imageName = uniqid() . '.' . $extension;
                $image->moveTo(__DIR__ . '/uploads/' . $imageName);
            }
        }


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
            ->withHeader('Location', $app->getBasePath() . '/admin/blog')
            ->withStatus(302);
            
    }

    return $twig->render($response, 'admin/blog/edit.twig', [
        'title' => 'Edit Blog',
        'blog' => $blog
    ]);
});
$app->get('/admin/blog/delete/{id}', function ($request, $response, $args) use ($twig, $pdo, $app) {

    $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->execute([$args['id']]);

    return $response
        ->withHeader('Location', $app->getBasePath() . '/admin/blog')
        ->withStatus(302);
});

// Sitemap Route
$app->get('/sitemap.xml', function ($request, $response) use ($pdo) {

    $baseUrl = 'http://localhost/ongoing/conair/public'; 
    // ⚠️ Change this to your correct localhost URL

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    $staticPages = [
    [
        'path' => '/',
        'priority' => '1.0',
        'changefreq' => 'daily'
    ],
    [
        'path' => '/about',
        'priority' => '0.8',
        'changefreq' => 'monthly'
    ],
    [
        'path' => '/services',
        'priority' => '0.8',
        'changefreq' => 'monthly'
    ],
    [
        'path' => '/contact',
        'priority' => '0.8',
        'changefreq' => 'monthly'
    ],
    [
        'path' => '/blog',
        'priority' => '0.9',
        'changefreq' => 'weekly'
    ],
];

foreach ($staticPages as $page) {
    $xml .= '<url>
        <loc>' . $baseUrl . $page['path'] . '</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>' . $page['changefreq'] . '</changefreq>
        <priority>' . $page['priority'] . '</priority>
    </url>';
}

    // Blog posts
    $stmt = $pdo->query("SELECT slug, created_at FROM blogs WHERE status='published'");
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($blogs as $blog) {
        $xml .= '<url>
            <loc>' . $baseUrl . '/blog/' . $blog['slug'] . '</loc>
            <lastmod>' . date('Y-m-d', strtotime($blog['created_at'])) . '</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>';
    }

    $xml .= '</urlset>';

    $response->getBody()->write($xml);

    return $response->withHeader('Content-Type', 'application/xml');
});




$app->run();
