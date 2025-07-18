<?php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Tools\Attributes\Tool;
use HelgeSverre\Swarm\Tools\Attributes\ToolParam;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class ToolSchemaGenerator
{
    /**
     * Generate OpenAI function schemas from a tool class
     */
    public static function generateSchemas(object|string $toolClass): array
    {
        $reflection = new ReflectionClass($toolClass);
        $schemas = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor and non-tool methods
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }

            // Check for Tool attribute
            $toolAttributes = $method->getAttributes(Tool::class);
            if (empty($toolAttributes)) {
                continue;
            }

            /** @var Tool $toolAttribute */
            $toolAttribute = $toolAttributes[0]->newInstance();

            $schema = [
                'name' => $toolAttribute->name,
                'description' => $toolAttribute->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ];

            // Process method parameters
            foreach ($method->getParameters() as $parameter) {
                $paramSchema = self::generateParameterSchema($parameter);
                if ($paramSchema) {
                    $schema['parameters']['properties'][$parameter->getName()] = $paramSchema['schema'];
                    if ($paramSchema['required']) {
                        $schema['parameters']['required'][] = $parameter->getName();
                    }
                }
            }

            $schemas[] = $schema;
        }

        return $schemas;
    }

    /**
     * Generate schemas for multiple tool classes
     */
    public static function generateSchemasForClasses(array $toolClasses): array
    {
        $allSchemas = [];

        foreach ($toolClasses as $toolClass) {
            $schemas = self::generateSchemas($toolClass);
            $allSchemas = array_merge($allSchemas, $schemas);
        }

        return $allSchemas;
    }

    /**
     * Generate schema for a single parameter
     */
    private static function generateParameterSchema(ReflectionParameter $parameter): ?array
    {
        // Check for ToolParam attribute
        $paramAttributes = $parameter->getAttributes(ToolParam::class);

        if (! empty($paramAttributes)) {
            /** @var ToolParam $paramAttribute */
            $paramAttribute = $paramAttributes[0]->newInstance();

            $schema = [
                'description' => $paramAttribute->description,
            ];

            // Determine type
            $type = $paramAttribute->type;
            if (! $type && $parameter->hasType()) {
                $reflectionType = $parameter->getType();
                if ($reflectionType && ! $reflectionType->isBuiltin()) {
                    $type = 'string'; // Default for non-builtin types
                } else {
                    $type = match ($reflectionType?->getName()) {
                        'int', 'float' => 'number',
                        'bool' => 'boolean',
                        'array' => 'array',
                        'string', null => 'string',
                        default => 'string',
                    };
                }
            }
            $schema['type'] = $type ?: 'string';

            // Add enum if specified
            if ($paramAttribute->enum) {
                $schema['enum'] = $paramAttribute->enum;
            }

            // Add default if specified
            if ($paramAttribute->default !== null) {
                $schema['default'] = $paramAttribute->default;
            }

            return [
                'schema' => $schema,
                'required' => $paramAttribute->required && ! $parameter->isOptional(),
            ];
        }

        // If no attribute, try to infer from parameter
        if ($parameter->hasType()) {
            $reflectionType = $parameter->getType();
            $type = match ($reflectionType?->getName()) {
                'int', 'float' => 'number',
                'bool' => 'boolean',
                'array' => 'array',
                'string', null => 'string',
                default => 'string',
            };

            return [
                'schema' => [
                    'type' => $type,
                    'description' => $parameter->getName(),
                ],
                'required' => ! $parameter->isOptional(),
            ];
        }

        return null;
    }
}
