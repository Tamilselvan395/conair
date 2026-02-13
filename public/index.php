<?php
session_start();

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use App\Core\Database;
use App\Controllers\HomeController;
use App\Controllers\BlogController;
use App\Controllers\AdminController;
use App\Controllers\SitemapController;
use App\Controllers\PageController;
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
| Core Middleware
|--------------------------------------------------------------------------
*/
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware(); // Required for POST forms

/*
|--------------------------------------------------------------------------
| Twig Setup
|--------------------------------------------------------------------------
*/
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('base_url', $basePath);
$twig->getEnvironment()->addGlobal('session', $_SESSION);

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
$pageController    = new PageController($twig, $pdo , $basePath);
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

$app->get('/about', [$pageController, 'about']);
$app->get('/contact', [$pageController, 'contact']);
$app->post('/contact', [$pageController, 'contactSubmit']);

$app->get('/services', [$pageController, 'services']);
$app->get('/services/{slug}', [$pageController, 'serviceDetail']);

/*
|--------------------------------------------------------------------------
| Admin Login (No CSRF)
|--------------------------------------------------------------------------
*/
$app->map(['GET','POST'], '/admin/login', [$adminController, 'login']);
$app->post('/admin/logout', [$adminController, 'logout']);

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/
$app->group('/admin', function ($group) use ($adminController) {

    $group->get('', [$adminController, 'dashboard']);
    $group->get('/blog', [$adminController, 'blogList']);
    $group->map(['GET','POST'], '/blog/create', [$adminController, 'createBlog']);
    $group->map(['GET','POST'], '/blog/edit/{id}', [$adminController, 'editBlog']);
    $group->post('/blog/delete/{id}', [$adminController, 'deleteBlog']);
    $group->get('/contacts', [$adminController, 'contactList']);
    $group->post('/contacts/delete/{id}', [$adminController, 'deleteContact']);

})
->add($authMiddleware);

/*
|--------------------------------------------------------------------------
| Sitemap
|--------------------------------------------------------------------------
*/
$app->get('/sitemap.xml', [$sitemapController, 'index']);

$app->run();
