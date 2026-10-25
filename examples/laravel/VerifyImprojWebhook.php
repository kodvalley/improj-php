<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kodvalley\Improj\Webhook\SignatureVerificationException;
use Kodvalley\Improj\Webhook\WebhookVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware that verifies IMPROJ webhook signatures.
 *
 * config/services.php:
 *     'improj' => ['webhook_secret' => env('IMPROJ_WEBHOOK_SECRET')],
 *
 * routes/api.php (API routes have no CSRF middleware; never put the webhook under the web group):
 *     Route::post('/webhooks/improj', ImprojWebhookController::class)
 *         ->middleware(\App\Http\Middleware\VerifyImprojWebhook::class);
 *
 * In the controller, read the verified event from the request attributes:
 *     $event = $request->attributes->get('improj_event'); // Kodvalley\Improj\Model\WebhookEvent
 * then dispatch a queued job and return response()->noContent().
 */
final class VerifyImprojWebhook
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.improj.webhook_secret');
        if (!\is_string($secret) || $secret === '') {
            abort(500, 'IMPROJ webhook secret is not configured.');
        }
        if ((int) $request->header('Content-Length', '0') > 65536) {
            abort(413);
        }

        try {
            // getContent() is the raw body, exactly as received.
            $event = (new WebhookVerifier($secret))->constructEvent($request->getContent(), $request->header('IMPROJ-Signature'));
        } catch (SignatureVerificationException $e) {
            logger()->warning('IMPROJ webhook refused', ['reason' => $e->getReason()]);
            abort(400);
        }

        // Retries deliver the same event again: remember delivery ids for a day.
        if (!cache()->add('improj-delivery:' . $event->deliveryId, true, now()->addDay())) {
            return response()->noContent();
        }

        $request->attributes->set('improj_event', $event);

        return $next($request);
    }
}
