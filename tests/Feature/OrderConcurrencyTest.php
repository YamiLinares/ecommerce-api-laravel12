<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifica el bloqueo de filas real de InnoDB (MySQL) con una segunda conexión.
 * Usa truncado en lugar de transacciones para que ambas conexiones vean los datos.
 */
class OrderConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseTruncation limpia antes de cada prueba; se limpia también al final
        // para no dejar datos confirmados a las clases que usan RefreshDatabase.
        $this->beforeApplicationDestroyed(fn () => $this->truncateTablesForAllConnections());
    }

    public function test_order_waits_for_row_lock_held_by_concurrent_purchase(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());

        $customer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        config(['database.connections.mysql_concurrent' => config('database.connections.mysql')]);
        $other = DB::connection('mysql_concurrent');

        // Otra "compra" mantiene bloqueada la fila del producto.
        $other->beginTransaction();
        $other->select('select id from products where id = ? for update', [$product->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->jwtHeaders($customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVENTORY_BUSY');

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(0, Order::count());

        // Al liberar el bloqueo, la compra se completa.
        $other->rollBack();
        $other->disconnect();

        $this->resetAuth();
        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->jwtHeaders($customer))
            ->assertCreated();

        $this->assertSame(0, $product->fresh()->stock);
    }
}
