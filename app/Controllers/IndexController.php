<?php

namespace App\Controllers;

class IndexController
{
    public function index()
    {
        require __DIR__ . '/../Views/frontend/index.php';
    }
}