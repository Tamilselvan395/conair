<?php
session_start();

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Csrf\Guard;

use App\Core\Database;
use App\Controllers\HomeController;
use App\Controllers\BlogController;
use App\Controllers\AdminController;
use App\Controllers\SitemapController;
use App\Middleware\AuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

/*
|--------------------------------------------------------------------------
| Base Path
|--------------------------------------------------------------------------
*/
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$app->setBasePath($basePath);

/*
|--------------------------------------------------------------------------
| Middleware Order (VERY IMPORTANT)
|--------------------------------------------------------------------------
*/
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

/*
|--------------------------------------------------------------------------
| Twig
|--------------------------------------------------------------------------
*/
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('base_url', $basePath);

$app->add(TwigMiddleware::create($app, $twig));

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
$pdo = Database::connect();

/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/
$homeController    = new HomeController($twig, $pdo);
$blogController    = new BlogController($twig, $pdo);
$adminController   = new AdminController($twig, $pdo, $basePath);
$sitemapController = new SitemapController($pdo, $basePath);

$authMiddleware = new AuthMiddleware();

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
$app->get('/', [$homeController, 'index']);
$app->get('/blog', [$blogController, 'index']);
$app->get('/blog/{slug}', [$blogController, 'show']);

/*
|--------------------------------------------------------------------------
| Login Routes (NO CSRF HERE)
|--------------------------------------------------------------------------
*/
$app->map(['GET','POST'], '/admin/login', [$adminController, 'login']);
$app->post('/admin/logout', [$adminController, 'logout']);

/*
|--------------------------------------------------------------------------
| Admin Routes (Protected + CSRF)
|--------------------------------------------------------------------------
*/
$app->group('/admin', function ($group) use ($adminController) {

    $group->get('', [$adminController, 'dashboard']);
    $group->get('/blog', [$adminController, 'blogList']);
    $group->map(['GET','POST'], '/blog/create', [$adminController, 'createBlog']);
    $group->map(['GET','POST'], '/blog/edit/{id}', [$adminController, 'editBlog']);
    $group->post('/blog/delete/{id}', [$adminController, 'deleteBlog']);

})
->add(new Guard($app->getResponseFactory())) // CSRF only here
->add($authMiddleware);

/*
|--------------------------------------------------------------------------
| Sitemap
|--------------------------------------------------------------------------
*/
$app->get('/sitemap.xml', [$sitemapController, 'index']);

$app->run();
