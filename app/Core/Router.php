<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($basePath !== '/' && $basePath !== '.' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        $path = '/' . trim($path, '/');
        $path = $path === '//' ? '/' : $path;

        foreach ($this->routes[$method] ?? [] as $route) {
            $parameterNames = [];
            $parts = preg_split(
                '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
                $route['path'],
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );
            $pattern = '';

            foreach ($parts ?: [] as $part) {
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $parameter)) {
                    $parameterNames[] = $parameter[1];
                    $pattern .= '([0-9]+)';
                } else {
                    $pattern .= preg_quote($part, '#');
                }
            }

            if (!is_string($pattern) || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }

            $parameters = [];
            foreach ($parameterNames as $index => $name) {
                $parameters[$name] = (int) $matches[$index + 1];
            }

            $this->invoke($route['handler'], $parameters);
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Page not found']);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $normalizedPath = '/' . trim($path, '/');
        $this->routes[$method][] = [
            'path' => $normalizedPath === '//' ? '/' : $normalizedPath,
            'handler' => $handler,
        ];
    }

    private function invoke(callable|array $handler, array $parameters = []): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            $controller = new $handler[0]();
            $controller->{$handler[1]}(...array_values($parameters));
            return;
        }

        call_user_func_array($handler, array_values($parameters));
    }
}
