<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Commands;

use Illuminate\Console\Command;
use Shaqi\ReverseKit\Generators\ControllerGenerator;
use Shaqi\ReverseKit\Generators\FactoryGenerator;
use Shaqi\ReverseKit\Generators\FormRequestGenerator;
use Shaqi\ReverseKit\Generators\MigrationGenerator;
use Shaqi\ReverseKit\Generators\ModelGenerator;
use Shaqi\ReverseKit\Generators\PolicyGenerator;
use Shaqi\ReverseKit\Generators\ResourceGenerator;
use Shaqi\ReverseKit\Generators\RouteGenerator;
use Shaqi\ReverseKit\Generators\SeederGenerator;
use Shaqi\ReverseKit\Generators\TestGenerator;
use Shaqi\ReverseKit\Parsers\ApiUrlParser;
use Shaqi\ReverseKit\Parsers\DatabaseParser;
use Shaqi\ReverseKit\Parsers\JsonParser;
use Shaqi\ReverseKit\Parsers\OpenApiParser;
use Shaqi\ReverseKit\Parsers\PostmanParser;

class ReverseGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reverse:generate
                            {input? : Path to JSON file or literal JSON string (optional if using --from-* options)}
                            {--from-url= : Fetch JSON from API URL}
                            {--from-openapi= : Parse OpenAPI/Swagger specification file}
                            {--from-postman= : Parse Postman collection file}
                            {--from-database=* : Reverse from database tables (comma-separated or * for all)}
                            {--auth-token= : Bearer token for API URL authentication}
                            {--module= : Module/domain name prefix}
                            {--namespace=App : Custom namespace}
                            {--force : Overwrite existing files without confirmation}
                            {--preview : Preview what will be generated without writing files}
                            {--only= : Only generate specific types (comma-separated: model,migration,controller,resource,request,policy,factory,seeder,test,routes)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate Laravel backend scaffolding from JSON structure';

    /**
     * Generated files tracking.
     */
    private array $generatedFiles = [];

    /**
     * Execute the console command.
     */
    public function handle(
        JsonParser $jsonParser,
        ApiUrlParser $apiUrlParser,
        OpenApiParser $openApiParser,
        PostmanParser $postmanParser,
        DatabaseParser $databaseParser,
        ModelGenerator $modelGenerator,
        MigrationGenerator $migrationGenerator,
        ControllerGenerator $controllerGenerator,
        ResourceGenerator $resourceGenerator,
        FormRequestGenerator $formRequestGenerator,
        PolicyGenerator $policyGenerator,
        FactoryGenerator $factoryGenerator,
        SeederGenerator $seederGenerator,
        TestGenerator $testGenerator,
        RouteGenerator $routeGenerator
    ): int {
        $this->info('🚀 Laravel ReverseKit - Backend Scaffolding Generator');
        $this->info('   by Shaqi Labs');
        $this->newLine();

        // Get options
        $namespace = $this->option('namespace') ?? 'App';
        $module = $this->option('module') ?? '';
        $force = $this->option('force') ?? false;
        $preview = $this->option('preview') ?? false;
        $only = $this->option('only') ? explode(',', $this->option('only')) : [];

        try {
            $entities = $this->parseFromSource(
                $jsonParser,
                $apiUrlParser,
                $openApiParser,
                $postmanParser,
                $databaseParser
            );
        } catch (\Exception $e) {
            $this->error('❌ Failed to parse input: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($entities)) {
            $this->warn('⚠️ No entities found in JSON structure.');
            return Command::FAILURE;
        }

        $this->info('📋 Found ' . count($entities) . ' entities: ' . implode(', ', array_keys($entities)));
        $this->newLine();

        if ($preview) {
            $this->warn('👁️ PREVIEW MODE - No files will be written');
            $this->newLine();
        }

        // Configure all generators
        $allGenerators = [
            'model' => $modelGenerator,
            'migration' => $migrationGenerator,
            'controller' => $controllerGenerator,
            'resource' => $resourceGenerator,
            'request' => $formRequestGenerator,
            'policy' => $policyGenerator,
            'factory' => $factoryGenerator,
            'seeder' => $seederGenerator,
            'test' => $testGenerator,
        ];

        // Filter generators based on --only option and config
        $generators = $this->filterGenerators($allGenerators, $only);

        foreach ($generators as $generator) {
            $generator->setNamespace($namespace)
                ->setModule($module)
                ->setAllEntities($entities);
        }

        $routeGenerator->setNamespace($namespace)
            ->setModule($module)
            ->setAllEntities($entities);

        // Sort entities to ensure parent entities are generated first
        $sortedEntities = $this->sortEntitiesByDependency($entities);

        // Preview mode - just show what would be generated
        if ($preview) {
            $this->previewGeneration($sortedEntities, $generators, $only);
            return Command::SUCCESS;
        }

        // Check for existing files if not forcing
        if (!$force) {
            $existingFiles = $this->checkExistingFiles($entities, $generators);
            if (!empty($existingFiles)) {
                $this->warn('⚠️ The following files already exist:');
                foreach ($existingFiles as $file) {
                    $this->line("   - {$file}");
                }
                $this->newLine();

                if (!$this->confirm('Do you want to overwrite these files?', false)) {
                    $this->info('Operation cancelled.');
                    return Command::SUCCESS;
                }
                $force = true;
            }
        }

        // Generate files for each entity
        foreach ($sortedEntities as $entityName => $entity) {
            $this->info("📦 Generating scaffolding for: {$entityName}");

            foreach ($generators as $type => $generator) {
                $result = $generator->generate($entity, $force);
                $this->trackResult($type, $result);
            }
        }

        // Generate routes (all at once) if enabled
        if (empty($only) || in_array('routes', $only)) {
            $this->info('🛣️ Generating routes...');
            $routeResults = $routeGenerator->generateAll(array_values($sortedEntities), $force);
            foreach ($routeResults as $entityName => $result) {
                $this->trackResult('Routes', $result, $entityName);
            }
        }

        // Display summary
        $this->displaySummary();

        $this->newLine();
        $this->info('✅ Scaffolding generation complete!');

        return Command::SUCCESS;
    }

    /**
     * Filter generators based on --only option and config.
     */
    private function filterGenerators(array $allGenerators, array $only): array
    {
        $filtered = [];

        foreach ($allGenerators as $key => $generator) {
            // Check if this type is enabled in config
            $enabled = config("reversekit.generators.{$key}", true);

            // If --only is specified, check if this type is included
            if (!empty($only)) {
                $enabled = in_array($key, $only);
            }

            if ($enabled) {
                $filtered[$key] = $generator;
            }
        }

        return $filtered;
    }

    /**
     * Preview what would be generated.
     */
    private function previewGeneration(array $entities, array $generators, array $only): void
    {
        $this->info('📋 The following files would be generated:');
        $this->newLine();

        foreach ($entities as $entityName => $_entity) {
            $this->line("   <fg=cyan>{$entityName}</>:");

            foreach ($generators as $type => $_generator) {
                $this->line("      • {$type}");
            }
        }

        if (empty($only) || in_array('routes', $only)) {
            $this->line('   <fg=cyan>Routes</>:');
            $this->line('      • api.php (apiResource routes)');
        }

        $this->newLine();
        $this->info('Run without --preview to generate files.');
    }

    /**
     * Parse from the appropriate source based on options.
     */
    private function parseFromSource(
        JsonParser $jsonParser,
        ApiUrlParser $apiUrlParser,
        OpenApiParser $openApiParser,
        PostmanParser $postmanParser,
        DatabaseParser $databaseParser
    ): array {
        // Check for --from-url option
        if ($url = $this->option('from-url')) {
            $this->info("📡 Fetching from API URL: {$url}");
            $authToken = $this->option('auth-token');

            if ($authToken) {
                return $apiUrlParser->parseWithAuth($url, $authToken);
            }

            return $apiUrlParser->parse($url);
        }

        // Check for --from-openapi option
        if ($openApiFile = $this->option('from-openapi')) {
            $this->info("📄 Parsing OpenAPI specification: {$openApiFile}");
            return $openApiParser->parse($openApiFile);
        }

        // Check for --from-postman option
        if ($postmanFile = $this->option('from-postman')) {
            $this->info("📮 Parsing Postman collection: {$postmanFile}");
            return $postmanParser->parse($postmanFile);
        }

        // Check for --from-database option
        $dbTables = $this->option('from-database');
        if (!empty($dbTables)) {
            $tables = is_array($dbTables) ? implode(',', $dbTables) : $dbTables;
            if (empty($tables)) {
                $tables = '*';
            }
            $this->info("🗄️ Reverse engineering from database tables: {$tables}");
            return $databaseParser->parse($tables);
        }

        // Default: JSON input (file or string)
        $input = $this->argument('input');

        if (!$input) {
            throw new \InvalidArgumentException(
                'Please provide a JSON file/string or use one of: --from-url, --from-openapi, --from-postman, --from-database'
            );
        }

        return $this->parseJsonInput($jsonParser, $input);
    }

    /**
     * Parse JSON input (file path or string).
     */
    private function parseJsonInput(JsonParser $parser, string $input): array
    {
        // Check if input is a file path
        if (file_exists($input)) {
            return $parser->parseFile($input);
        }

        // Check if input looks like JSON
        $trimmed = trim($input);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return $parser->parse($input);
        }

        // Try as a relative path from base
        $fullPath = base_path($input);
        if (file_exists($fullPath)) {
            return $parser->parseFile($fullPath);
        }

        throw new \InvalidArgumentException(
            "Input must be a valid JSON string or path to a JSON file. File not found: {$input}"
        );
    }

    /**
     * Check for existing files.
     */
    private function checkExistingFiles(array $entities, array $generators): array
    {
        $existing = [];

        foreach ($entities as $entity) {
            foreach ($generators as $generator) {
                $result = $generator->generate($entity, false);
                $this->collectExistingFiles($result, $existing);
            }
        }

        return array_unique($existing);
    }

    /**
     * Collect existing files from generator result.
     */
    private function collectExistingFiles(string|array $result, array &$existing): void
    {
        if (is_string($result)) {
            if (str_starts_with($result, 'skipped:')) {
                $existing[] = substr($result, 8);
            }
            return;
        }

        // Handle array results (e.g., FormRequestGenerator returns store/update)
        foreach ($result as $subResult) {
            $this->collectExistingFiles($subResult, $existing);
        }
    }

    /**
     * Sort entities by dependency (parents first).
     */
    private function sortEntitiesByDependency(array $entities): array
    {
        $sorted = [];
        $remaining = $entities;

        while (!empty($remaining)) {
            foreach ($remaining as $name => $entity) {
                $parent = $entity['parent'] ?? null;

                // If no parent or parent already sorted, add to sorted
                if ($parent === null || isset($sorted[$parent])) {
                    $sorted[$name] = $entity;
                    unset($remaining[$name]);
                }
            }
        }

        return $sorted;
    }

    /**
     * Track generation result.
     */
    private function trackResult(string $type, string|array $result, ?string $entityName = null): void
    {
        // Handle string results (new format)
        if (is_string($result)) {
            $skipped = str_starts_with($result, 'skipped:');
            $path = $skipped ? substr($result, 8) : $result;
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

            $this->generatedFiles[] = [
                'type' => $type,
                'path' => $relativePath,
                'created' => !$skipped,
                'existed' => $skipped,
            ];

            $status = $skipped ? '○' : '✓';
            $label = $entityName ? "{$type} ({$entityName})" : $type;
            $this->line("   {$status} {$label}: {$relativePath}");
            return;
        }

        // Handle array results (for generators returning multiple files)
        if (isset($result['store']) || isset($result['update'])) {
            foreach ($result as $subType => $subResult) {
                $this->trackResult("{$type}:{$subType}", $subResult, $entityName);
            }
            return;
        }

        // Handle legacy array format
        $status = $result['created'] ? '✓' : ($result['existed'] ? '○' : '✗');
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $result['path']);

        $this->generatedFiles[] = [
            'type' => $type,
            'path' => $relativePath,
            'created' => $result['created'],
            'existed' => $result['existed'],
        ];

        $label = $entityName ? "{$type} ({$entityName})" : $type;
        $this->line("   {$status} {$label}: {$relativePath}");
    }

    /**
     * Display generation summary.
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 Generation Summary:');

        $created = count(array_filter($this->generatedFiles, fn ($f) => $f['created']));
        $skipped = count(array_filter($this->generatedFiles, fn ($f) => $f['existed'] && !$f['created']));

        $this->line("   ✓ Created: {$created} files");
        $this->line("   ○ Skipped: {$skipped} files (already existed)");
    }
}
