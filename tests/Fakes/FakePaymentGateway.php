<?php

namespace Tests\Fakes;

use App\Exceptions\ApiException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentIntentData;

/**
 * Doble de Stripe para pruebas: no realiza llamadas de red y registra las operaciones.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, array{status:string, amount:int, currency:string, error:?string}> */
    public array $intents = [];

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public bool $configured = true;

    public bool $failNetwork = false;

    /** Estado que devolverá Stripe al confirmar con un método de pago. */
    public string $confirmStatus = 'succeeded';

    public ?string $confirmError = null;

    private int $sequence = 0;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createPaymentIntent(int $amountCents, string $currency, array $metadata, string $idempotencyKey, ?string $paymentMethod = null): PaymentIntentData
    {
        $this->guard();
        $this->calls[] = ['op' => 'create', 'amount' => $amountCents, 'currency' => $currency, 'metadata' => $metadata, 'idempotency_key' => $idempotencyKey, 'payment_method' => $paymentMethod];

        $id = 'pi_fake_'.(++$this->sequence);
        $this->intents[$id] = ['status' => 'requires_payment_method', 'amount' => $amountCents, 'currency' => $currency, 'error' => null];

        if ($paymentMethod !== null) {
            $this->applyConfirm($id);
        }

        return $this->data($id);
    }

    public function retrievePaymentIntent(string $id): PaymentIntentData
    {
        $this->guard();
        $this->calls[] = ['op' => 'retrieve', 'id' => $id];

        return $this->data($id);
    }

    public function confirmPaymentIntent(string $id, string $paymentMethod): PaymentIntentData
    {
        $this->guard();
        $this->calls[] = ['op' => 'confirm', 'id' => $id, 'payment_method' => $paymentMethod];
        $this->applyConfirm($id);

        return $this->data($id);
    }

    public function cancelPaymentIntent(string $id): PaymentIntentData
    {
        $this->guard();
        $this->calls[] = ['op' => 'cancel', 'id' => $id];
        $this->intents[$id]['status'] = 'canceled';

        return $this->data($id);
    }

    public function setStatus(string $id, string $status): void
    {
        $this->intents[$id]['status'] = $status;
    }

    public function operations(): array
    {
        return array_column($this->calls, 'op');
    }

    private function applyConfirm(string $id): void
    {
        if ($this->confirmError !== null) {
            $this->intents[$id]['status'] = 'requires_payment_method';
            $this->intents[$id]['error'] = $this->confirmError;

            return;
        }

        $this->intents[$id]['status'] = $this->confirmStatus;
        $this->intents[$id]['error'] = null;
    }

    private function guard(): void
    {
        if (! $this->configured) {
            throw ApiException::paymentsNotConfigured();
        }
        if ($this->failNetwork) {
            throw ApiException::paymentGateway();
        }
    }

    private function data(string $id): PaymentIntentData
    {
        $intent = $this->intents[$id];

        return new PaymentIntentData($id, $intent['status'], $id.'_secret_test', $intent['amount'], $intent['currency'], $intent['error']);
    }
}
