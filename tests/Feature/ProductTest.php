<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function validProduct(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'tec-100',
            'name' => 'Micrófono USB',
            'description' => 'Micrófono de condensador.',
            'price' => '64.90',
            'stock' => 25,
        ], $overrides);
    }

    public function test_product_listing_is_public_and_paginated(): void
    {
        Product::factory()->count(20)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonStructure(['data' => [['id', 'sku', 'name', 'description', 'price', 'stock', 'is_active', 'created_at', 'updated_at']], 'links', 'meta']);
    }

    public function test_listing_hides_inactive_products_from_public(): void
    {
        Product::factory()->create(['name' => 'Visible']);
        Product::factory()->inactive()->create(['name' => 'Oculto']);

        $this->getJson('/api/products?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible');

        $this->resetAuth();
        $this->getJson('/api/products?include_inactive=1', $this->jwtHeaders(User::factory()->admin()->create()))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_listing_supports_filters_and_sorting(): void
    {
        Product::factory()->create(['name' => 'Teclado barato', 'price' => '10.00', 'stock' => 5]);
        Product::factory()->create(['name' => 'Teclado caro', 'price' => '150.00', 'stock' => 0]);
        Product::factory()->create(['name' => 'Monitor', 'price' => '300.00', 'stock' => 2]);

        $this->getJson('/api/products?search=teclado&sort=-price')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Teclado caro');

        $this->getJson('/api/products?min_price=100&max_price=200')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', '150.00');

        $this->getJson('/api/products?in_stock=1')
            ->assertOk()->assertJsonCount(2, 'data');

        // Los comodines de LIKE se tratan como texto literal.
        $this->getJson('/api/products?search=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_listing_rejects_invalid_filters(): void
    {
        $this->getJson('/api/products?per_page=500&sort=password&min_price=50&max_price=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort', 'max_price']);
    }

    public function test_product_can_be_viewed_publicly(): void
    {
        $product = Product::factory()->create(['price' => '19.99']);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', '19.99');
    }

    public function test_inactive_or_missing_product_returns_404(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$inactive->id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/api/products/999999')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/products', $this->validProduct(), $this->jwtHeaders($admin))
            ->assertCreated()
            ->assertJsonPath('data.sku', 'TEC-100')
            ->assertJsonPath('data.price', '64.90')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', ['sku' => 'TEC-100', 'price' => '64.90', 'stock' => 25]);
    }

    public function test_product_creation_validates_input(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->postJson('/api/products', [
            'sku' => 'DUP-1',
            'name' => '',
            'price' => '10.999',
            'stock' => -1,
        ], $this->jwtHeaders($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'price', 'stock']);

        $this->resetAuth();
        $this->postJson('/api/products', $this->validProduct(['price' => '0.10']), $this->jwtHeaders($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_customer_cannot_manage_products(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $headers = $this->jwtHeaders($customer);

        $this->postJson('/api/products', $this->validProduct(), $headers)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->putJson("/api/products/{$product->id}", ['price' => '1.00'], $headers)->assertForbidden();
        $this->deleteJson("/api/products/{$product->id}", [], $headers)->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_guest_cannot_manage_products(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/products', $this->validProduct())->assertUnauthorized();
        $this->patchJson("/api/products/{$product->id}", ['price' => '1.00'])->assertUnauthorized();
        $this->deleteJson("/api/products/{$product->id}")->assertUnauthorized();
    }

    public function test_admin_can_update_product_partially(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Original', 'price' => '10.00', 'stock' => 3]);

        $this->patchJson("/api/products/{$product->id}", ['price' => '12.50', 'is_active' => false], $this->jwtHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.name', 'Original')
            ->assertJsonPath('data.price', '12.50')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}", [], $this->jwtHeaders($admin))->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }
}
