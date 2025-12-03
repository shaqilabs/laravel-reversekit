<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class ResourceGenerator extends BaseGenerator
{
    /**
     * Generate a resource file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $resourceName = $entity['name'] . 'Resource';
        $path = $this->getBasePath() . '/Http/Resources/' . $resourceName . '.php';

        if (! $force && $this->fileExists($path)) {
            return "skipped:{$path}";
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Generate resource content.
     */
    private function generateContent(array $entity): string
    {
        $modelName = $entity['name'];
        $resourceName = $modelName . 'Resource';
        $namespace = $this->getNamespace('Http\\Resources');

        $fields = $this->generateFieldsArray($entity);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

/**
 * @mixin \\{$this->getNamespace('Models')}\\{$modelName}
 */
class {$resourceName} extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return {$fields};
    }
}
PHP;
    }

    /**
     * Generate fields array for resource.
     */
    private function generateFieldsArray(array $entity): string
    {
        $lines = [];

        // Add regular fields
        foreach ($entity['fields'] as $fieldName => $field) {
            // Skip foreign keys when relationship is present
            if (str_ends_with($fieldName, '_id')) {
                $relationName = str_replace('_id', '', $fieldName);
                if (isset($entity['relationships'][$relationName])) {
                    continue;
                }
            }

            $lines[] = "            '{$fieldName}' => \$this->{$fieldName},";
        }

        // Add relationships
        foreach ($entity['relationships'] ?? [] as $name => $relation) {
            $method = $relation['method'];
            $relatedResource = $relation['related'] . 'Resource';

            if ($relation['type'] === 'hasMany') {
                $lines[] = "            '{$name}' => {$relatedResource}::collection(\$this->whenLoaded('{$method}')),";
            } else {
                $lines[] = "            '{$name}' => new {$relatedResource}(\$this->whenLoaded('{$method}')),";
            }
        }

        if (empty($lines)) {
            return '[]';
        }

        $fieldsString = implode("\n", $lines);

        return "[\n{$fieldsString}\n        ]";
    }
}
