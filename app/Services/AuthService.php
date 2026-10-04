<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\NotificationRepository;
use App\Core\Database;
use Throwable;

final class AuthService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function validateRegistration(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = preg_replace('/[\s()-]+/', '', trim((string) ($input['phone'] ?? ''))) ?? '';
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Name must contain between 2 and 100 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($email)) {
            $errors['email'] = 'An account already exists for this email address.';
        }

        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number containing 10 to 15 digits.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must contain at least 8 characters.';
        }

        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return [$errors, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ]];
    }

    public function register(array $data, string $role): int
    {
        $status = $role === 'organizer' ? 'pending' : 'approved';
        $database = Database::connection();
        $database->beginTransaction();

        try {
            $userId = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => $role,
                'account_status' => $status,
            ]);

            if ($role === 'organizer') {
                (new NotificationRepository())->createForApprovedAdmins(
                    'organizer_registration_submitted',
                    'New organizer application',
                    $data['name'] . ' submitted an organizer application.'
                );
            }

            $database->commit();
            return $userId;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        if ($user['account_status'] === 'disabled') {
            return null;
        }

        return $user;
    }
}
