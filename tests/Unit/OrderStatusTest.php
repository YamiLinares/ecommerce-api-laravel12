<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_valid_transitions(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped));
        $this->assertTrue(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_invalid_transitions(): void
    {
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Paid->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Paid->canTransitionTo(OrderStatus::Pending));

        foreach (OrderStatus::cases() as $next) {
            $this->assertFalse(OrderStatus::Delivered->canTransitionTo($next));
            $this->assertFalse(OrderStatus::Cancelled->canTransitionTo($next));
        }
    }

    public function test_paid_is_not_admin_assignable(): void
    {
        $this->assertNotContains('paid', OrderStatus::adminAssignable());
        $this->assertNotContains('pending', OrderStatus::adminAssignable());
    }
}
