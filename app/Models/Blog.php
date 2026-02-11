<?php

namespace App\Models;

use PDO;

class Blog
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function latest($limit = 3)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM blogs
            WHERE status = 'published'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function all()
    {
        return $this->pdo->query("
            SELECT * FROM blogs
            WHERE status = 'published'
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug($slug)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM blogs
            WHERE slug = ? AND status='published'
        ");
        $stmt->execute([$slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
