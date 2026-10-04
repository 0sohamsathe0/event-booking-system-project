<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BookingRepository;

final class HomeController
{
    public function index(): void
    {
        View::render('home/index', [
            'pageTitle' => 'Discover events',
            'events' => (new BookingRepository())->publicEvents(),
        ]);
    }
}
