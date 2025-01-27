<?php

namespace App\Controllers\Auth;

use App\Models\User;
use App\Helpers\Helper;

class LoginController
{
    private $userModel;
    
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const REQUIRED_FIELDS = ['email', 'password', 'csrf_token'];

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $timeoutDuration = 1800;

        if (isset($_SESSION['LAST_ACTIVITY'])) {
            if (time() - $_SESSION['LAST_ACTIVITY'] > $timeoutDuration) {
                session_unset();
                session_destroy();
                return Helper::redirect('login', ['error' => 'Session expired due to inactivity.']);
            }
        }

        $_SESSION['LAST_ACTIVITY'] = time();

        if (Helper::isPostRequest()) {
            return $this->handleLogin();
        } else {
            return Helper::view('frontend/login.php');
        }
    }

    public function handleLogin()
    {
        session_start();
        
        $formData = $this->sanitizeLoginData($_POST);
        $errors = $this->validateLoginData($formData);

        if (!empty($errors)) {
            return Helper::view('frontend/login.php', [
                'errors' => $errors,
                'formData' => $formData
            ]);
        }

        try {
            if ($this->authenticateUser($formData)) {
                $this->resetLoginAttempts();
                return Helper::redirect('dashboard');
            }
            
            $this->handleFailedLogin();
            
        } catch (\Exception $e) {
            return Helper::view('frontend/login.php', [
                'error' => 'An error occurred during login.',
                'formData' => $formData
            ]);
        }
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        Helper::redirect('login', ['success' => 'You have been logged out successfully.']);
    }

    private function authenticateUser(array $formData): bool
    {
        $user = $this->userModel->getUserByEmail($formData['email']);
        
        if ($user && password_verify($formData['password'], $user['password'])) {
            $this->setUserSession($user);
            return true;
        }
        
        return false;
    }

    private function handleFailedLogin(): void
    {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        
        $error = $_SESSION['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS
            ? 'Too many failed attempts. Please try again later.'
            : 'Invalid email or password.';

        Helper::view('frontend/login.php', ['error' => $error]);
    }

    private function sanitizeLoginData(array $data): array
    {
        return [
            'email' => filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'password' => trim($data['password'] ?? ''),
            'csrf_token' => $data['csrf_token'] ?? ''
        ];
    }

    private function validateLoginData(array $data): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . ' is required.';
            }
        }

        // CSRF validation
        if (!verifyCsrfToken($data['csrf_token'])) {
            $errors['csrf'] = 'Invalid CSRF token. Please try again.';
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        return $errors;
    }

    private function setUserSession(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
    }

    private function resetLoginAttempts(): void
    {
        if (isset($_SESSION['login_attempts'])) {
            unset($_SESSION['login_attempts']);
        }
    }
} 