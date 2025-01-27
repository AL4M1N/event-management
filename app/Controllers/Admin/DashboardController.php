<?php

namespace App\Controllers\Admin;

class DashboardController
{
    public function index()
    {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }

        $userId = $_SESSION['user_id'];
        $name = $_SESSION['name'];
        $email = $_SESSION['email'];

        require __DIR__ . '/../../Views/backend/dashboard.php';
    }
}