<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

use Illuminate\Filesystem\Filesystem;
use Shaqi\ReverseKit\Support\RelationshipDetector;

abstract class BaseGenerator
{
    protected Filesystem $filesystem;
    protected RelationshipDetector $relationshipDetector;
    protected string $namespace = 'App';
    protected string $module = '';

    public function __construct()
    {
        $this->filesystem = new Filesystem();
        $this->relationshipDetector = new RelationshipDetector(
            new \Shaqi\ReverseKit\Support\TypeInferrer()
        );
    }

    /**
     * Set the namespace prefix.
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = rtrim($namespace, '\\');
        return $this;
    }

    /**
     * Set the module/domain prefix.
     */
    public function setModule(string $module): self
    {
        $this->module = $module;
        return $this;
    }

    /**
     * Get the full namespace.
     */
    protected function getNamespace(string $suffix = ''): string
    {
        $parts = [$this->namespace];

        if (!empty($this->module)) {
            $parts[] = $this->relationshipDetector->toPascalCase($this->module);
        }

        if (!empty($suffix)) {
            $parts[] = $suffix;
        }

        return implode('\\', $parts);
    }

    /**
     * Get the base path for generated files.
     */
    protected function getBasePath(): string
    {
        $basePath = app_path();

        if (!empty($this->module)) {
            $basePath .= '/' . $this->relationshipDetector->toPascalCase($this->module);
        }

        return $basePath;
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Write content to file.
     */
    protected function writeFile(string $path, string $content, bool $force = false): bool
    {
        if ($this->filesystem->exists($path) && !$force) {
            return false;
        }

        $this->ensureDirectoryExists($path);
        $this->filesystem->put($path, $content);

        return true;
    }

    /**
     * Check if file exists.
     */
    protected function fileExists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    /**
     * Generate file from entity data.
     *
     * @param array $entity Entity metadata
     * @param bool $force Overwrite existing files
     * @return string|array
     */
    abstract public function generate(array $entity, bool $force = false): string|array;

    /**
     * Get all entities for cross-referencing.
     */
    protected array $allEntities = [];

    /**
     * Set all entities for relationship generation.
     */
    public function setAllEntities(array $entities): self
    {
        $this->allEntities = $entities;
        return $this;
    }

    /**
     * Indent code block.
     */
    protected function indent(string $code, int $levels = 1): string
    {
        $indent = str_repeat('    ', $levels);
        $lines = explode("\n", $code);
        return implode("\n", array_map(fn ($line) => $line ? $indent . $line : $line, $lines));
    }

    /**
     * Get stub content.
     */
    protected function getStub(string $name): string
    {
        // Check for custom stub first
        $customPath = config('reversekit.stub_path', resource_path('stubs/reversekit'));
        $customStub = "{$customPath}/{$name}.stub";

        if ($this->filesystem->exists($customStub)) {
            return $this->filesystem->get($customStub);
        }

        // Fall back to package stub
        $packageStub = __DIR__ . "/../../stubs/{$name}.stub";

        if ($this->filesystem->exists($packageStub)) {
            return $this->filesystem->get($packageStub);
        }

        throw new \RuntimeException("Stub file not found: {$name}.stub");
    }

    /**
     * Get the path for a generated file.
     * Override in child classes for custom paths.
     */
    protected function getPath(string $className): string
    {
        return $this->getBasePath() . '/' . $className . '.php';
    }
}
