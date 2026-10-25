<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Procesa webhooks de Stripe: verifica la firma, garantiza idempotencia
 * y actualiza pagos y órdenes de forma transaccional.
 */
class StripeWebhookService
{
    public const HANDLED_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.processing',
        'payment_intent.canceled',
    ];

    /**
     * @return array{event_id: string, type: string, duplicate: bool}
     */
    public function handle(string $payload, ?string $signature): array
    {
        $event = $this->verify($payload, $signature);

        try {
            $duplicate = DB::transaction(function () use ($event) {
                // Registrar el evento dentro de la misma transacción: si el procesamiento falla,
                // el registro se revierte y Stripe puede reintentar el envío.
                if (StripeWebhookEvent::where('stripe_event_id', $event->id)->lockForUpdate()->exists()) {
                    return true;
                }

                StripeWebhookEvent::create([
                    'stripe_event_id' => $event->id,
                    'type' => $event->type,
                    'processed_at' => now(),
                ]);

                if (in_array($event->type, self::HANDLED_EVENTS, true)) {
                    $this->applyPaymentIntentEvent($event);
                }

                return false;
            });
        } catch (UniqueConstraintViolationException) {
            // Otro proceso registró el mismo evento en paralelo.
            $duplicate = true;
        }

        return ['event_id' => $event->id, 'type' => $event->type, 'duplicate' => $duplicate];
    }

    private function verify(string $payload, ?string $signature): Event
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new ApiException('Webhook de Stripe no configurado.', 503, 'WEBHOOK_NOT_CONFIGURED');
        }

        if (blank($signature)) {
            throw new ApiException('Falta la cabecera Stripe-Signature.', 400, 'INVALID_SIGNATURE');
        }

        try {
            return Webhook::constructEvent($payload, $signature, $secret, (int) config('services.stripe.webhook_tolerance'));
        } catch (SignatureVerificationException) {
            throw new ApiException('Firma del webhook inválida.', 400, 'INVALID_SIGNATURE');
        } catch (UnexpectedValueException) {
            throw new ApiException('Contenido del webhook inválido.', 400, 'INVALID_PAYLOAD');
        }
    }

    private function applyPaymentIntentEvent(Event $event): void
    {
        $intent = $event->data->object;

        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->lockForUpdate()->first();

        if ($payment === null) {
            Log::warning('Webhook de Stripe para un PaymentIntent desconocido', [
                'event_id' => $event->id,
                'payment_intent' => $intent->id,
            ]);

            return;
        }

        $metadataPaymentId = $intent->metadata['payment_id'] ?? null;
        if ($metadataPaymentId !== null && (string) $metadataPaymentId !== (string) $payment->id) {
            Log::error('Metadata del PaymentIntent no coincide con el pago local', [
                'event_id' => $event->id,
                'payment_id' => $payment->id,
            ]);

            return;
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->markSucceeded($payment, $intent, $event->id),
            'payment_intent.payment_failed' => $this->markFailed($payment, $intent->last_payment_error?->message),
            'payment_intent.processing' => $this->markProcessing($payment),
            'payment_intent.canceled' => $this->markCanceled($payment),
        };
    }

    private function markSucceeded(Payment $payment, object $intent, string $eventId): void
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        // Verificación adicional: el importe cobrado debe coincidir con el pago registrado.
        $expectedCents = Money::toCents($payment->amount);
        if ((int) $intent->amount_received !== $expectedCents || strtolower($intent->currency) !== $payment->currency) {
            Log::critical('Importe cobrado por Stripe no coincide con el pago', [
                'event_id' => $eventId,
                'payment_id' => $payment->id,
                'expected_cents' => $expectedCents,
                'received_cents' => (int) $intent->amount_received,
            ]);
            $payment->update(['status' => PaymentStatus::Failed, 'failure_message' => 'Importe recibido no coincide con la orden.']);

            return;
        }

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'failure_message' => null,
            'paid_at' => now(),
        ]);

        $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        if ($order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

            return;
        }

        Log::critical('Pago completado para una orden que no está pendiente; requiere revisión o reembolso', [
            'event_id' => $eventId,
            'order_id' => $order->id,
            'order_status' => $order->status->value,
            'payment_id' => $payment->id,
        ]);
    }

    private function markFailed(Payment $payment, ?string $message): void
    {
        if ($payment->status->isFinal()) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'failure_message' => $message !== null ? mb_substr($message, 0, 255) : 'Pago rechazado.',
        ]);
    }

    private function markProcessing(Payment $payment): void
    {
        if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            $payment->update(['status' => PaymentStatus::Processing]);
        }
    }

    private function markCanceled(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Succeeded) {
            $payment->update(['status' => PaymentStatus::Canceled]);
        }
    }
}
