<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, phone, password_hash, role, account_status,
                    review_reason
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users
                (name, email, phone, password_hash, role, account_status)
             VALUES
                (:name, :email, :phone, :password_hash, :role, :account_status)'
        );
        $statement->execute($data);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateCustomerProfile(int $customerId, string $name, string $phone): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE users
             SET name = :name, phone = :phone
             WHERE id = :id AND role = 'customer'"
        );
        $statement->execute([
            'id' => $customerId,
            'name' => $name,
            'phone' => $phone,
        ]);
    }
}
