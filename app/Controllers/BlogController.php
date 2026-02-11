<?php

namespace App\Controllers;

use App\Models\Blog;
use Slim\Exception\HttpNotFoundException;

class BlogController
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
        $blogs = $this->blog->all();

        return $this->twig->render($response, 'blog.twig', [
            'title' => 'Blog',
            'blogs' => $blogs
        ]);
    }

    public function show($request, $response, $args)
    {
        $blog = $this->blog->findBySlug($args['slug']);

        if (!$blog) {
            throw new HttpNotFoundException($request);
        }

        return $this->twig->render($response, 'blog_detail.twig', [
            'title' => $blog['meta_title'],
            'meta_description' => $blog['meta_description'],
            'blog' => $blog
        ]);
    }
}
