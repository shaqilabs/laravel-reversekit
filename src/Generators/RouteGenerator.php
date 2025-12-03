<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class RouteGenerator extends BaseGenerator
{
    /**
     * Generate routes and append to api.php.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $path = base_path('routes/api.php');
        $routeContent = $this->generateRouteContent($entity);

        // Check if route already exists
        $existed = $this->routeExists($path, $entity['name']);

        if ($existed && !$force) {
            return "skipped:{$path}";
        }

        // Append route to api.php
        $this->appendRoute($path, $routeContent);

        return $path;
    }

    /**
     * Generate route content for an entity.
     */
    private function generateRouteContent(array $entity): string
    {
        $modelName = $entity['name'];
        $controllerClass = $this->getNamespace('Http\\Controllers') . '\\' . $modelName . 'Controller';
        $routeName = $this->relationshipDetector->pluralize(
            $this->relationshipDetector->toSnakeCase($modelName)
        );
        // Convert underscores to hyphens for URL
        $routePath = str_replace('_', '-', $routeName);

        return "\nRoute::apiResource('{$routePath}', \\{$controllerClass}::class);";
    }

    /**
     * Generate all routes for multiple entities.
     */
    public function generateAll(array $entities, bool $force = false): array
    {
        $results = [];
        $path = base_path('routes/api.php');

        $routes = [];
        foreach ($entities as $entity) {
            if ($this->routeExists($path, $entity['name']) && !$force) {
                $results[$entity['name']] = "skipped:{$path}";
                continue;
            }

            $routes[] = $this->generateRouteContent($entity);
            $results[$entity['name']] = $path;
        }

        if (!empty($routes)) {
            $routeBlock = $this->generateRouteBlock($entities, $routes);
            $this->appendRoute($path, $routeBlock);
        }

        return $results;
    }

    /**
     * Generate a grouped route block.
     */
    private function generateRouteBlock(array $entities, array $routes): string
    {
        $modelNames = implode(', ', array_map(fn ($e) => $e['name'], $entities));
        $timestamp = date('Y-m-d H:i:s');

        $block = "\n\n// ReverseKit Generated Routes ({$timestamp})";
        $block .= "\n// Entities: {$modelNames}";
        $block .= implode('', $routes);

        return $block;
    }

    /**
     * Check if route for entity already exists.
     */
    private function routeExists(string $path, string $modelName): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        $controllerClass = $modelName . 'Controller';

        return str_contains($content, $controllerClass);
    }

    /**
     * Append route to api.php file.
     */
    private function appendRoute(string $path, string $routeContent): void
    {
        if (!file_exists($path)) {
            $this->createApiRouteFile($path, $routeContent);
            return;
        }

        file_put_contents($path, $routeContent, FILE_APPEND);
    }

    /**
     * Create api.php file if it doesn't exist.
     */
    private function createApiRouteFile(string $path, string $routeContent): void
    {
        $content = <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;
{$routeContent}
PHP;

        $this->ensureDirectoryExists($path);
        file_put_contents($path, $content);
    }
}
