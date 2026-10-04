<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\HallRepository;

final class AdminHallController
{
    public function edit(): void
    {
        Authorization::requireAdmin();
        $this->render((new HallRepository())->first() ?? $this->emptyHall(), []);
    }

    public function update(): void
    {
        Authorization::requireAdmin();

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            return;
        }

        [$errors, $data] = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->render($_POST, $errors);
            return;
        }

        (new HallRepository())->save($data);
        Session::flash('success', 'Hall settings saved. Organizers can now create events.');
        Authorization::redirect('admin/dashboard');
    }

    private function validate(array $input): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'maximum_capacity' => filter_var($input['maximum_capacity'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'contact_phone' => trim((string) ($input['contact_phone'] ?? '')) ?: null,
            'contact_email' => strtolower(trim((string) ($input['contact_email'] ?? ''))) ?: null,
        ];
        $errors = [];

        if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 150) {
            $errors['name'] = 'Hall name must contain between 2 and 150 characters.';
        }
        if ($data['address'] === '' || mb_strlen($data['address']) > 255) {
            $errors['address'] = 'Enter a valid hall address.';
        }
        if ($data['city'] === '' || mb_strlen($data['city']) > 100) {
            $errors['city'] = 'Enter a valid city.';
        }
        if ($data['maximum_capacity'] < 1) {
            $errors['maximum_capacity'] = 'Maximum capacity must be at least 1.';
        } else {
            $requiredCapacity = (new HallRepository())->highestScheduledEventCapacity();
            if ($data['maximum_capacity'] < $requiredCapacity) {
                $errors['maximum_capacity'] = 'Capacity cannot be below the existing event capacity of '
                    . $requiredCapacity . '.';
            }
        }
        if ($data['contact_phone'] !== null
            && !preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/[\s()-]+/', '', $data['contact_phone']) ?? '')) {
            $errors['contact_phone'] = 'Enter a valid phone number or leave it blank.';
        }
        if ($data['contact_email'] !== null && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Enter a valid contact email or leave it blank.';
        }

        return [$errors, $data];
    }

    private function render(array $hall, array $errors): void
    {
        View::render('admin/hall/edit', [
            'pageTitle' => 'Hall settings',
            'hall' => $hall,
            'errors' => $errors,
        ]);
    }

    private function emptyHall(): array
    {
        return ['name' => '', 'address' => '', 'city' => '', 'maximum_capacity' => '',
            'contact_phone' => '', 'contact_email' => ''];
    }
}
