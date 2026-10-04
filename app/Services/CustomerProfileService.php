<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class CustomerProfileService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function validate(array $input): array
    {
        $errors = [];
        $name = trim($this->stringValue($input, 'name'));
        $phone = preg_replace('/[\s()-]+/', '', trim($this->stringValue($input, 'phone'))) ?? '';

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Name must contain between 2 and 100 characters.';
        }

        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number containing 10 to 15 digits.';
        }

        return [$errors, ['name' => $name, 'phone' => $phone]];
    }

    public function update(int $customerId, array $data): void
    {
        $this->users->updateCustomerProfile($customerId, $data['name'], $data['phone']);
    }

    private function stringValue(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? $value : '';
    }
}
