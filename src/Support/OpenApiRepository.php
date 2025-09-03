<?php

declare(strict_types=1);

namespace McpDocs\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class OpenApiRepository
{
    protected array $schema = [];

    protected bool $loaded = false;

    public function __construct(
        protected string $openApiPath
    ) {}

    /**
     * Get the full OpenAPI schema.
     */
    public function getSchema(): array
    {
        $this->ensureLoaded();

        return $this->schema;
    }

    /**
     * Get endpoint details for a specific path and method.
     */
    public function getEndpointDetails(string $path, string $method): ?array
    {
        $this->ensureLoaded();

        $paths = $this->schema['paths'] ?? [];
        $normalizedMethod = strtolower($method);

        if (! isset($paths[$path][$normalizedMethod])) {
            return null;
        }

        $endpoint = $paths[$path][$normalizedMethod];

        return [
            'path' => $path,
            'method' => strtoupper($method),
            'summary' => $endpoint['summary'] ?? null,
            'description' => $endpoint['description'] ?? null,
            'operationId' => $endpoint['operationId'] ?? null,
            'tags' => $endpoint['tags'] ?? [],
            'parameters' => $this->processParameters($endpoint['parameters'] ?? []),
            'requestBody' => $this->processRequestBody($endpoint['requestBody'] ?? null),
            'responses' => $this->processResponses($endpoint['responses'] ?? []),
            'security' => $endpoint['security'] ?? [],
        ];
    }

    /**
     * Get all available paths and methods.
     */
    public function getAvailableEndpoints(): array
    {
        $this->ensureLoaded();

        $endpoints = [];
        $paths = $this->schema['paths'] ?? [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $details) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'])) {
                    $endpoints[] = [
                        'path' => $path,
                        'method' => strtoupper($method),
                        'summary' => $details['summary'] ?? null,
                        'operationId' => $details['operationId'] ?? null,
                        'tags' => $details['tags'] ?? [],
                    ];
                }
            }
        }

        return $endpoints;
    }

    /**
     * Generate mock response for an endpoint.
     */
    public function getMockResponse(string $path, string $method, int $statusCode = 200): ?array
    {
        $endpoint = $this->getEndpointDetails($path, $method);

        if (! $endpoint) {
            return null;
        }

        $responses = $endpoint['responses'] ?? [];
        $responseKey = (string) $statusCode;

        if (! isset($responses[$responseKey])) {
            // Try to find the first 2xx response
            foreach ($responses as $code => $response) {
                if (str_starts_with($code, '2')) {
                    $responseKey = $code;
                    break;
                }
            }
        }

        if (! isset($responses[$responseKey])) {
            return null;
        }

        $response = $responses[$responseKey];
        $content = $response['content'] ?? [];

        if (isset($content['application/json']['schema'])) {
            return $this->generateMockFromSchema($content['application/json']['schema']);
        }

        return null;
    }

    /**
     * Ensure the OpenAPI schema is loaded.
     */
    protected function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if (! File::exists($this->openApiPath)) {
            throw new \RuntimeException("OpenAPI file not found: {$this->openApiPath}");
        }

        $content = File::get($this->openApiPath);

        try {
            $this->schema = Yaml::parse($content);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to parse OpenAPI file: {$e->getMessage()}");
        }

        $this->loaded = true;
    }

    /**
     * Process parameters array.
     */
    protected function processParameters(array $parameters): array
    {
        return array_map(function ($param) {
            return [
                'name' => $param['name'] ?? null,
                'in' => $param['in'] ?? null,
                'description' => $param['description'] ?? null,
                'required' => $param['required'] ?? false,
                'schema' => $param['schema'] ?? null,
                'example' => $param['example'] ?? null,
            ];
        }, $parameters);
    }

    /**
     * Process request body.
     */
    protected function processRequestBody(?array $requestBody): ?array
    {
        if (! $requestBody) {
            return null;
        }

        return [
            'description' => $requestBody['description'] ?? null,
            'required' => $requestBody['required'] ?? false,
            'content' => $requestBody['content'] ?? [],
        ];
    }

    /**
     * Process responses.
     */
    protected function processResponses(array $responses): array
    {
        $processed = [];

        foreach ($responses as $code => $response) {
            $processed[$code] = [
                'description' => $response['description'] ?? null,
                'content' => $response['content'] ?? [],
                'headers' => $response['headers'] ?? [],
            ];
        }

        return $processed;
    }

    /**
     * Generate mock data from a schema.
     */
    protected function generateMockFromSchema(array $schema): mixed
    {
        $type = $schema['type'] ?? 'object';

        return match ($type) {
            'string' => $this->mockString($schema),
            'integer', 'number' => $this->mockNumber($schema),
            'boolean' => $this->mockBoolean($schema),
            'array' => $this->mockArray($schema),
            'object' => $this->mockObject($schema),
            default => null,
        };
    }

    /**
     * Generate mock string.
     */
    protected function mockString(array $schema): string
    {
        if (isset($schema['example'])) {
            return (string) $schema['example'];
        }

        if (isset($schema['enum'])) {
            return (string) $schema['enum'][0];
        }

        $format = $schema['format'] ?? null;

        return match ($format) {
            'email' => 'user@example.com',
            'date' => '2024-01-01',
            'date-time' => '2024-01-01T00:00:00Z',
            'uri', 'url' => 'https://example.com',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            default => 'string',
        };
    }

    /**
     * Generate mock number.
     */
    protected function mockNumber(array $schema): int|float
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $type = $schema['type'] ?? 'number';

        return $type === 'integer' ? 123 : 123.45;
    }

    /**
     * Generate mock boolean.
     */
    protected function mockBoolean(array $schema): bool
    {
        return $schema['example'] ?? true;
    }

    /**
     * Generate mock array.
     */
    protected function mockArray(array $schema): array
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $items = $schema['items'] ?? ['type' => 'string'];

        return [$this->generateMockFromSchema($items)];
    }

    /**
     * Generate mock object.
     */
    protected function mockObject(array $schema): array
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $properties = $schema['properties'] ?? [];
        $mock = [];

        foreach ($properties as $name => $property) {
            $mock[$name] = $this->generateMockFromSchema($property);
        }

        return $mock;
    }
}
