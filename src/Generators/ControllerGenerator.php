<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class ControllerGenerator extends BaseGenerator
{
    /**
     * Generate a controller file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $controllerName = $entity['name'] . 'Controller';
        $path = $this->getBasePath() . '/Http/Controllers/' . $controllerName . '.php';

        if (! $force && $this->fileExists($path)) {
            return "skipped:{$path}";
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Generate controller content.
     */
    private function generateContent(array $entity): string
    {
        $modelName = $entity['name'];
        $controllerName = $modelName . 'Controller';
        $resourceName = $modelName . 'Resource';
        $namespace = $this->getNamespace('Http\\Controllers');
        $modelNamespace = $this->getNamespace('Models') . '\\' . $modelName;
        $resourceNamespace = $this->getNamespace('Http\\Resources') . '\\' . $resourceName;

        $modelVar = lcfirst($modelName);
        $modelPlural = $this->relationshipDetector->pluralize(strtolower($modelName));

        // Get fillable fields for validation
        $validationRules = $this->generateValidationRules($entity['fields']);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelNamespace};
use {$resourceNamespace};
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection;

class {$controllerName} extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        \${$modelPlural} = {$modelName}::paginate();

        return {$resourceName}::collection(\${$modelPlural});
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request \$request): JsonResponse
    {
        \$validated = \$request->validate({$validationRules});

        \${$modelVar} = {$modelName}::create(\$validated);

        return (new {$resourceName}(\${$modelVar}))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show({$modelName} \${$modelVar}): {$resourceName}
    {
        return new {$resourceName}(\${$modelVar});
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request \$request, {$modelName} \${$modelVar}): {$resourceName}
    {
        \$validated = \$request->validate({$validationRules});

        \${$modelVar}->update(\$validated);

        return new {$resourceName}(\${$modelVar});
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy({$modelName} \${$modelVar}): JsonResponse
    {
        \${$modelVar}->delete();

        return response()->json(null, 204);
    }
}
PHP;
    }

    /**
     * Generate validation rules array.
     */
    private function generateValidationRules(array $fields): string
    {
        $rules = [];

        foreach ($fields as $fieldName => $field) {
            // Skip non-fillable fields
            if (in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $fieldRules = $this->getFieldRules($fieldName, $field);
            if (!empty($fieldRules)) {
                $rules[] = "            '{$fieldName}' => '{$fieldRules}',";
            }
        }

        if (empty($rules)) {
            return '[]';
        }

        $rulesString = implode("\n", $rules);
        return "[\n{$rulesString}\n        ]";
    }

    /**
     * Get validation rules for a field.
     */
    private function getFieldRules(string $fieldName, array $field): string
    {
        $rules = [];
        $migration = $field['migration'];
        $nullable = $migration['nullable'] ?? false;

        $rules[] = $nullable ? 'nullable' : 'required';

        $type = $migration['type'];
        $rules[] = match ($type) {
            'string' => 'string|max:255',
            'text' => 'string',
            'integer', 'bigInteger' => 'integer',
            'unsignedBigInteger' => 'integer|min:1',
            'boolean' => 'boolean',
            'decimal', 'float' => 'numeric',
            'date' => 'date',
            'datetime', 'timestamp' => 'date',
            'json' => 'array',
            'uuid' => 'uuid',
            default => 'string|max:255',
        };

        // Add email validation
        if ($fieldName === 'email') {
            $rules[] = 'email';
        }

        return implode('|', $rules);
    }
}
