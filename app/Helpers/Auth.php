<?php

function isAuthenticated()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuthentication()
{
    if (!isAuthenticated()) {
        header('Location: ' . base_url('login'));
        exit;
    }
}