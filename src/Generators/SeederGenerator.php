<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class SeederGenerator extends BaseGenerator
{
    /**
     * Generate a database seeder.
     *
     * @param array<string, mixed> $entity
     */
    public function generate(array $entity, bool $force = false): string
    {
        $name = $entity['name'];
        $className = "{$name}Seeder";
        $path = $this->getPath($className);

        if (! $force && $this->filesystem->exists($path)) {
            return "skipped:{$path}";
        }

        $stub = $this->getStub('seeder');
        $content = $this->buildContent($stub, $entity, $className);

        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Build the seeder content.
     */
    protected function buildContent(string $stub, array $entity, string $className): string
    {
        $name = $entity['name'];
        $modelNamespace = $this->getNamespace('Models');
        $count = $this->determineCount($entity);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ modelNamespace }}',
                '{{ model }}',
                '{{ class }}',
                '{{ count }}',
            ],
            [
                'Database\\Seeders',
                $modelNamespace,
                $name,
                $className,
                $count,
            ],
            $stub
        );
    }

    /**
     * Determine appropriate count based on entity type.
     */
    protected function determineCount(array $entity): int
    {
        $name = strtolower($entity['name']);

        // Main entities like Users get fewer records
        if (in_array($name, ['user', 'admin', 'category', 'setting'])) {
            return 10;
        }

        // Content-related entities get more records
        if (in_array($name, ['post', 'article', 'comment', 'review', 'order'])) {
            return 50;
        }

        return 25;
    }

    /**
     * Get the path for the seeder class.
     */
    protected function getPath(string $className): string
    {
        return database_path('seeders/' . $className . '.php');
    }

    /**
     * Generate DatabaseSeeder with all model seeders.
     *
     * @param array<string, array> $entities
     */
    public function generateDatabaseSeeder(array $entities, bool $force = false): string
    {
        $path = database_path('seeders/DatabaseSeeder.php');

        if (! $force && $this->filesystem->exists($path)) {
            return "skipped:{$path}";
        }

        $calls = [];
        foreach ($entities as $entity) {
            $calls[] = "            {$entity['name']}Seeder::class,";
        }

        $content = $this->buildDatabaseSeederContent($calls);

        $this->writeFile($path, $content);

        return $path;
    }

    /**
     * Build DatabaseSeeder content.
     */
    protected function buildDatabaseSeederContent(array $calls): string
    {
        $callsString = implode("\n", $calls);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \$this->call([
{$callsString}
        ]);
    }
}
PHP;
    }
}
