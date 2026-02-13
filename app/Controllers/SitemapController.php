<?php

namespace App\Controllers;

class SitemapController
{
    private $pdo;
    private $basePath;

    public function __construct($pdo, $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = $basePath;
    }

    public function index($request, $response)
    {
        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();

        $baseUrl = $scheme . '://' . $host . $this->basePath;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        /*
        |--------------------------------------------------------------------------
        | Static Pages
        |--------------------------------------------------------------------------
        */
        $staticPages = [
            '/',
            '/about',
            '/services',
            '/blog',
            '/contact'
        ];

        foreach ($staticPages as $page) {
            $xml .= '<url>
                <loc>' . $baseUrl . $page . '</loc>
                <lastmod>' . date('Y-m-d') . '</lastmod>
                <changefreq>weekly</changefreq>
                <priority>0.8</priority>
            </url>';
        }

        /*
        |--------------------------------------------------------------------------
        | Service Detail Pages (STATIC)
        |--------------------------------------------------------------------------
        */
        $serviceSlugs = [
            'electrical-maintenance-installation-company-uae',
            'mechanical-mep-contractors-uae',
            'plumbing-services-dubai'
        ];

        foreach ($serviceSlugs as $slug) {
            $xml .= '<url>
                <loc>' . $baseUrl . '/services/' . $slug . '</loc>
                <lastmod>' . date('Y-m-d') . '</lastmod>
                <changefreq>monthly</changefreq>
                <priority>0.7</priority>
            </url>';
        }

        /*
        |--------------------------------------------------------------------------
        | Blog Posts
        |--------------------------------------------------------------------------
        */
        $stmt = $this->pdo->query("
            SELECT slug, created_at
            FROM blogs
            WHERE status = 'published'
        ");

        $blogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($blogs as $blog) {
            $xml .= '<url>
                <loc>' . $baseUrl . '/blog/' . $blog['slug'] . '</loc>
                <lastmod>' . date('Y-m-d', strtotime($blog['created_at'])) . '</lastmod>
                <changefreq>weekly</changefreq>
                <priority>0.9</priority>
            </url>';
        }

        $xml .= '</urlset>';

        $response->getBody()->write($xml);

        return $response->withHeader('Content-Type', 'application/xml');
    }
}
