<?php

namespace App\Controllers;

use Slim\Exception\HttpNotFoundException;

class PageController
{
    private $twig;

    public function __construct($twig)
    {
        $this->twig = $twig;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Pages
    |--------------------------------------------------------------------------
    */

    public function about($request, $response)
    {
        return $this->twig->render($response, 'about.twig');
    }

    public function contact($request, $response)
    {
        return $this->twig->render($response, 'contact.twig');
    }

    /*
    |--------------------------------------------------------------------------
    | Services Main Page
    |--------------------------------------------------------------------------
    */

    public function services($request, $response)
    {
        return $this->twig->render($response, 'services/index.twig');
    }

    /*
    |--------------------------------------------------------------------------
    | Service Detail Pages (Static)
    |--------------------------------------------------------------------------
    */

    public function serviceDetail($request, $response, $args)
    {
        $slug = $args['slug'];

        // Path to twig file
        $filePath = __DIR__ . '/../../templates/services/' . $slug . '.twig';

        // If file does not exist → 404
        if (!file_exists($filePath)) {
            throw new HttpNotFoundException($request);
        }

        return $this->twig->render($response, "services/{$slug}.twig");
    }
}
