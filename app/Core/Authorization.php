<?php

declare(strict_types=1);

namespace App\Core;

final class Authorization
{
    private function __construct()
    {
    }

    public static function requireGuest(): void
    {
        if (Auth::check()) {
            self::redirect('account');
        }
    }

    public static function requireAuthentication(): void
    {
        if (!Auth::check() || !Auth::refresh()) {
            if (Auth::check()) {
                Auth::logout();
                Session::restart();
            }

            Session::flash('error', 'Please log in to continue.');
            self::redirect('login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuthentication();

        if (!Auth::hasRole(...$roles)) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Access denied']);
            exit;
        }
    }

    public static function requireApprovedOrganizer(): void
    {
        self::requireRole('organizer');

        if ((Auth::user()['account_status'] ?? null) !== 'approved') {
            http_response_code(403);
            View::render('errors/403', [
                'pageTitle' => 'Organizer approval required',
            ]);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRole('admin');

        if ((Auth::user()['account_status'] ?? null) !== 'approved') {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Access denied']);
            exit;
        }
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}
