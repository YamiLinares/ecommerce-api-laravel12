<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Evita IDOR: un cliente solo accede a sus propias órdenes.
 * Para órdenes ajenas responde 404 y así no revela su existencia.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $this->ownerOrAdmin($user, $order);
    }

    public function cancel(User $user, Order $order): Response
    {
        return $this->ownerOrAdmin($user, $order);
    }

    /** Solo el dueño de la orden puede pagarla. */
    public function pay(User $user, Order $order): Response
    {
        return $order->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    private function ownerOrAdmin(User $user, Order $order): Response
    {
        return $user->isAdmin() || $order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
