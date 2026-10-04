<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private function __construct()
    {
    }

    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/Views/' . $layout . '.php';

        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new RuntimeException('Requested view could not be found.');
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require $layoutFile;
    }
}

