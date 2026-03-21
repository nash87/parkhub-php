<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_openapi_json_spec_is_accessible(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertStatus(200);

        $spec = json_decode($response->getContent(), true);
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertStringStartsWith('3.', $spec['openapi']);
    }

    public function test_openapi_spec_contains_auth_endpoints(): void
    {
        $response = $this->get('/docs/api.json');
        $response->assertStatus(200);

        $spec = json_decode($response->getContent(), true);
        $paths = array_keys($spec['paths'] ?? []);
        $this->assertNotEmpty($paths, 'OpenAPI spec must contain at least one path');

        $allPaths = implode(' ', $paths);
        $this->assertStringContainsString('auth', $allPaths, 'OpenAPI spec must include auth endpoints');
    }

    public function test_openapi_spec_contains_booking_endpoints(): void
    {
        $response = $this->get('/docs/api.json');
        $response->assertStatus(200);

        $spec = json_decode($response->getContent(), true);
        $paths = array_keys($spec['paths'] ?? []);
        $allPaths = implode(' ', $paths);
        $this->assertStringContainsString('booking', $allPaths, 'OpenAPI spec must include booking endpoints');
    }

    public function test_swagger_ui_renders(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
    }
}
