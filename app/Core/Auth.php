<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = 'authenticated_user';

    private function __construct()
    {
    }

    public static function check(): bool
    {
        return is_array(Session::get(self::SESSION_KEY));
    }

    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_KEY);

        return is_array($user) ? $user : null;
    }

    public static function id(): ?int
    {
        $id = self::user()['id'] ?? null;

        return is_int($id) ? $id : null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'account_status' => $user['account_status'],
        ]);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function refresh(): bool
    {
        $userId = self::id();

        if ($userId === null) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, name, email, phone, role, account_status, review_reason
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!is_array($user) || $user['account_status'] === 'disabled') {
            return false;
        }

        Session::put(self::SESSION_KEY, [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'account_status' => $user['account_status'],
            'review_reason' => $user['review_reason'],
        ]);

        return true;
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::user()['role'] ?? null;

        return is_string($role) && in_array($role, $roles, true);
    }
}
