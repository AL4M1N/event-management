<?php

namespace App\Controllers\Auth;

use App\Models\User;
use App\Helpers\Helper;

class RegisterController
{
    private User $userModel;

    private const MINIMUM_NAME_LENGTH = 3;
    private const MAXIMUM_NAME_LENGTH = 50;
    private const MINIMUM_PASSWORD_LENGTH = 6;
    private const REQUIRED_FIELDS = ['name', 'email', 'password', 'confirm_password'];

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function index()
    {
        if (Helper::isPostRequest()) {
            return $this->handleRegistration();
        } else {
            return Helper::view('frontend/register.php');
        }
    }

    private function handleRegistration()
    {
        $formData = $this->sanitizeRegistrationData($_POST);
        $errors = $this->validateRegistrationData($formData);

        if (!empty($errors)) {
            return Helper::view('frontend/register.php', [
                'errors' => $errors,
                'formData' => $formData
            ]);
        }

        try {
            if ($this->userModel->emailExists($formData['email'])) {
                return Helper::view('frontend/register.php', [
                    'errors' => ['email' => 'Email already exists.'],
                    'formData' => $formData
                ]);
            }

            if ($this->createUser($formData)) {
                return Helper::view('frontend/register.php', [
                    'success' => 'Registration successful! You can now log in.',
                    'formData' => $formData
                ]);
            }

            throw new \Exception('Failed to create user account.');

        } catch (\Exception $e) {
            return Helper::view('frontend/register.php', [
                'error' => 'An error occurred while creating your account.',
                'formData' => $formData
            ]);
        }
    }

    private function createUser(array $formData): bool
    {
        $hashedPassword = password_hash($formData['password'], PASSWORD_BCRYPT);
        return $this->userModel->createUser(
            $formData['name'],
            $formData['email'],
            $hashedPassword
        );
    }

    private function sanitizeRegistrationData(array $data): array
    {
        return [
            'name' => trim($data['name'] ?? ''),
            'email' => filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'password' => $data['password'] ?? '',
            'confirm_password' => $data['confirm_password'] ?? ''
        ];
    }

    private function validateRegistrationData(array $data): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        // Name validation
        if (strlen($data['name']) < self::MINIMUM_NAME_LENGTH || 
            strlen($data['name']) > self::MAXIMUM_NAME_LENGTH) {
            $errors['name'] = sprintf(
                'Name must be between %d and %d characters.',
                self::MINIMUM_NAME_LENGTH,
                self::MAXIMUM_NAME_LENGTH
            );
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Password validation
        if (!empty($data['password'])) {
            if (strlen($data['password']) < self::MINIMUM_PASSWORD_LENGTH) {
                $errors['password'] = sprintf(
                    'Password must be at least %d characters long.',
                    self::MINIMUM_PASSWORD_LENGTH
                );
            }

            if ($data['password'] !== $data['confirm_password']) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }

        return $errors;
    }
}
