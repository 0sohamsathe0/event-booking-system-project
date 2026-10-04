<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$target = ($basePath === '' ? '' : $basePath) . '/public/';

header('Location: ' . $target, true, 302);
exit;
