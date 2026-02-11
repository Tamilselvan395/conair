<?php

namespace App\Middleware;

use Slim\Psr7\Response;

class AuthMiddleware
{
    public function __invoke($request, $handler)
    {
        if (!isset($_SESSION['admin'])) {
            $response = new Response();
            return $response
                ->withHeader('Location', $request->getUri()->getBasePath() . '/admin/login')
                ->withStatus(302);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}

