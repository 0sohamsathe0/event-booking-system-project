<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use PDOException;

final class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function showLogin(): void
    {
        Authorization::requireGuest();
        View::render('auth/login', [
            'pageTitle' => 'Log in',
            'errors' => [],
            'old' => ['email' => ''],
        ]);
    }

    public function login(): void
    {
        Authorization::requireGuest();

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            $this->invalidCsrf();
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        $user = $errors === [] ? $this->auth->attempt($email, $password) : null;

        if ($user === null && $errors === []) {
            $errors['credentials'] = 'The email or password is incorrect, or the account is unavailable.';
        }

        if ($errors !== []) {
            http_response_code(422);
            View::render('auth/login', [
                'pageTitle' => 'Log in',
                'errors' => $errors,
                'old' => ['email' => $email],
            ]);
            return;
        }

        Auth::login($user);
        Session::flash('success', 'Welcome back, ' . $user['name'] . '.');
        Authorization::redirect('account');
    }

    public function showCustomerRegistration(): void
    {
        $this->showRegistration('customer');
    }

    public function showOrganizerRegistration(): void
    {
        $this->showRegistration('organizer');
    }

    public function registerCustomer(): void
    {
        $this->register('customer');
    }

    public function registerOrganizer(): void
    {
        $this->register('organizer');
    }

    public function logout(): void
    {
        Authorization::requireAuthentication();

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            $this->invalidCsrf();
        }

        Auth::logout();
        header('Location: ' . url('login'));
        exit;
    }

    private function showRegistration(string $role): void
    {
        Authorization::requireGuest();
        View::render('auth/register', [
            'pageTitle' => $role === 'organizer' ? 'Organizer registration' : 'Create account',
            'registrationRole' => $role,
            'errors' => [],
            'old' => ['name' => '', 'email' => '', 'phone' => ''],
        ]);
    }

    private function register(string $role): void
    {
        Authorization::requireGuest();

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            $this->invalidCsrf();
        }

        [$errors, $data] = $this->auth->validateRegistration($_POST);

        if ($errors !== []) {
            http_response_code(422);
            View::render('auth/register', [
                'pageTitle' => $role === 'organizer' ? 'Organizer registration' : 'Create account',
                'registrationRole' => $role,
                'errors' => $errors,
                'old' => $data,
            ]);
            return;
        }

        try {
            $this->auth->register($data, $role);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            http_response_code(422);
            View::render('auth/register', [
                'pageTitle' => $role === 'organizer' ? 'Organizer registration' : 'Create account',
                'registrationRole' => $role,
                'errors' => ['email' => 'An account already exists for this email address.'],
                'old' => $data,
            ]);
            return;
        }

        $message = $role === 'organizer'
            ? 'Organizer application submitted. You can log in to track its approval status.'
            : 'Your account has been created. You can now log in.';
        Session::flash('success', $message);
        Authorization::redirect('login');
    }

    private function invalidCsrf(): never
    {
        http_response_code(419);
        View::render('errors/419', ['pageTitle' => 'Session expired']);
        exit;
    }
}

