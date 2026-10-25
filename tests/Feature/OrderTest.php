<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
    }

    public function test_order_requires_authentication(): void
    {
        $this->postJson('/api/orders', ['items' => []])->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_customer_creates_order_with_server_calculated_totals(): void
    {
        $a = Product::factory()->create(['price' => '19.99', 'stock' => 10]);
        $b = Product::factory()->create(['price' => '0.10', 'stock' => 5]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $a->id, 'quantity' => 3],
                ['product_id' => $b->id, 'quantity' => 3],
            ],
            // Campos manipulados por el cliente: deben ignorarse.
            'total' => '0.01',
            'user_id' => 999,
            'status' => 'paid',
        ], $this->jwtHeaders($this->customer));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.user_id', $this->customer->id)
            ->assertJsonPath('data.subtotal', '60.27')
            ->assertJsonPath('data.total', '60.27')
            ->assertJsonPath('data.currency', 'usd')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.unit_price', '19.99')
            ->assertJsonPath('data.items.0.subtotal', '59.97')
            ->assertJsonPath('data.items.1.subtotal', '0.30');

        $this->assertSame(7, $a->fresh()->stock);
        $this->assertSame(2, $b->fresh()->stock);
    }

    public function test_item_price_snapshot_is_preserved_after_product_price_changes(): void
    {
        $product = Product::factory()->create(['price' => '50.00', 'stock' => 5, 'name' => 'Nombre original']);

        $orderId = $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->jwtHeaders($this->customer))
            ->assertCreated()->json('data.id');

        $product->update(['price' => '75.00', 'name' => 'Nombre nuevo']);

        $this->resetAuth();
        $this->getJson("/api/orders/{$orderId}", $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.product_name', 'Nombre original')
            ->assertJsonPath('data.total', '50.00');
    }

    public function test_order_rejects_insufficient_stock_without_side_effects(): void
    {
        $ok = Product::factory()->create(['stock' => 10]);
        $scarce = Product::factory()->create(['stock' => 2, 'name' => 'Escaso']);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $ok->id, 'quantity' => 1],
                ['product_id' => $scarce->id, 'quantity' => 3],
            ],
        ], $this->jwtHeaders($this->customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('errors.product_id', $scarce->id)
            ->assertJsonPath('errors.available', 2)
            ->assertJsonPath('errors.requested', 3);

        // La transacción se revierte completa.
        $this->assertSame(10, $ok->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_order_total_above_payment_limit_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => '99999999.99', 'stock' => 10]);

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]], $this->jwtHeaders($this->customer))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'ORDER_TOTAL_TOO_LARGE');

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_last_unit_cannot_be_sold_twice(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $other = User::factory()->create();
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->postJson('/api/orders', $payload, $this->jwtHeaders($this->customer))->assertCreated();
        $this->resetAuth();
        $this->postJson('/api/orders', $payload, $this->jwtHeaders($other))->assertStatus(409);

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_order_locks_product_rows_for_update(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->jwtHeaders($this->customer))
            ->assertCreated();

        $this->assertNotEmpty(array_filter($queries, fn ($sql) => str_contains($sql, 'from `products`') && str_ends_with($sql, 'for update')));
    }

    public function test_order_validation_errors(): void
    {
        $inactive = Product::factory()->inactive()->create();
        $product = Product::factory()->create();
        $headers = $this->jwtHeaders($this->customer);

        $this->postJson('/api/orders', ['items' => []], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->resetAuth();
        $this->postJson('/api/orders', ['items' => [
            ['product_id' => $inactive->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 0],
            ['product_id' => $product->id, 'quantity' => 101, 'price' => '0.01'],
        ]], $this->jwtHeaders($this->customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.product_id', 'items.1.product_id', 'items.2.quantity',
                'items.2.product_id', 'items.3.quantity', 'items.3',
            ]);
    }

    public function test_customer_sees_only_own_order_history(): void
    {
        Order::factory()->count(2)->create(['user_id' => $this->customer->id]);
        Order::factory()->status(OrderStatus::Paid)->create(['user_id' => $this->customer->id]);
        Order::factory()->count(4)->create();

        $this->getJson('/api/orders', $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->resetAuth();
        $this->getJson('/api/orders?status=paid', $this->jwtHeaders($this->customer))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'paid');
    }

    public function test_customer_cannot_access_another_customers_order(): void
    {
        $foreign = Order::factory()->create();
        $headers = $this->jwtHeaders($this->customer);

        $this->getJson("/api/orders/{$foreign->id}", $headers)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->resetAuth();
        $this->postJson("/api/orders/{$foreign->id}/cancel", [], $this->jwtHeaders($this->customer))->assertNotFound();
        $this->resetAuth();
        $this->getJson("/api/orders/{$foreign->id}/payments", $this->jwtHeaders($this->customer))->assertNotFound();
        $this->resetAuth();
        $this->postJson("/api/orders/{$foreign->id}/payments", [], $this->jwtHeaders($this->customer))->assertNotFound();

        $this->assertSame(OrderStatus::Pending, $foreign->fresh()->status);
    }

    public function test_admin_can_view_any_order(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/orders/{$order->id}", $this->jwtHeaders(User::factory()->admin()->create()))
            ->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $orderId = $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 4]]], $this->jwtHeaders($this->customer))
            ->json('data.id');
        $this->assertSame(1, $product->fresh()->stock);

        $this->resetAuth();
        $this->postJson("/api/orders/{$orderId}/cancel", [], $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertNotNull(Order::find($orderId)->cancelled_at);

        // Cancelar de nuevo no vuelve a sumar stock.
        $this->resetAuth();
        $this->postJson("/api/orders/{$orderId}/cancel", [], $this->jwtHeaders($this->customer))
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_paid_order_cannot_be_cancelled_by_customer(): void
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create(['user_id' => $this->customer->id]);

        $this->postJson("/api/orders/{$order->id}/cancel", [], $this->jwtHeaders($this->customer))
            ->assertStatus(409)
            ->assertJsonPath('errors.from', 'paid');
    }

    public function test_admin_order_status_transitions(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->status(OrderStatus::Paid)->create();

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'], $this->jwtHeaders($admin))
            ->assertOk()->assertJsonPath('data.status', 'shipped');

        $this->resetAuth();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'], $this->jwtHeaders($admin))
            ->assertOk()->assertJsonPath('data.status', 'delivered');

        $this->resetAuth();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'], $this->jwtHeaders($admin))
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_admin_cannot_skip_states_or_mark_paid_manually(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'], $this->jwtHeaders($admin))
            ->assertStatus(409);

        $this->resetAuth();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'], $this->jwtHeaders($admin))
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_admin_endpoints_are_forbidden_for_customers(): void
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        $this->getJson('/api/admin/orders', $this->jwtHeaders($this->customer))->assertForbidden();
        $this->resetAuth();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'], $this->jwtHeaders($this->customer))
            ->assertForbidden();
    }

    public function test_admin_can_list_all_orders_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->count(2)->create(['user_id' => $this->customer->id]);
        Order::factory()->count(3)->create();

        $this->getJson('/api/admin/orders', $this->jwtHeaders($admin))->assertOk()->assertJsonPath('meta.total', 5);
        $this->resetAuth();
        $this->getJson("/api/admin/orders?user_id={$this->customer->id}", $this->jwtHeaders($admin))
            ->assertOk()->assertJsonPath('meta.total', 2);
    }
}
