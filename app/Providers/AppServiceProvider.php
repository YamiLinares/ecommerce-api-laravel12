<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => new StripePaymentGateway(
            (string) config('services.stripe.secret'),
            config('services.stripe.api_base') ?: null,
        ));
    }

    public function boot(): void
    {
        // Detecta carga diferida, atributos inexistentes y asignación masiva descartada fuera de producción.
        Model::shouldBeStrict(! $this->app->isProduction());

        JsonResource::wrap('data');

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Login y registro: limita por IP y por combinación correo + IP contra fuerza bruta.
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('auth:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by('orders:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by('payments:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(300)->by('webhooks:'.$request->ip());
        });
    }
}
