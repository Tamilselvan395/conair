<?php

namespace App\Core;

use PDO;

class Database
{
    public static function connect()
    {
        $pdo = new PDO("mysql:host=localhost;dbname=conair", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
