<?php

namespace Tests\Feature;

use Tests\TestCase;

class CollectionsTest extends TestCase
{
    public function test_collections_endpoint_returns_array_with_expected_keys(): void
    {
        $response = $this->getJson('/api/v1/collections');

        $response->assertStatus(200);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);

        foreach ($payload as $collection) {
            $this->assertArrayHasKey('id', $collection);
            $this->assertArrayHasKey('name', $collection);
            $this->assertArrayHasKey('url', $collection);
        }
    }
}
