<?php

namespace App\Controllers;

use App\Models\Blog;

class HomeController
{
    private $twig;
    private $blog;

    public function __construct($twig, $pdo)
    {
        $this->twig = $twig;
        $this->blog = new Blog($pdo);
    }

    public function index($request, $response)
    {
        $blogs = $this->blog->latest(3);

        return $this->twig->render($response, 'home.twig', [
            'title' => 'Home',
            'blogs' => $blogs
        ]);
    }
}
