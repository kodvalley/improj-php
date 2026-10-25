<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\WebhookDelivery;
use Kodvalley\Improj\Model\WebhookEndpoint;
use Kodvalley\Improj\Model\WebhookEndpointWithSecret;
use Kodvalley\Improj\Model\WebhookTestResult;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Webhook endpoints (scope webhooks:manage). Verify deliveries with
 * Kodvalley\Improj\Webhook\WebhookVerifier.
 *
 * @psalm-type EndpointInput = array{url?: string, description?: string, events?: list<string>, project_ids?: list<int>|null, enabled?: bool}
 */
final class WebhooksResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<WebhookEndpoint>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/webhooks', $params, WebhookEndpoint::fromArray(...), $options);
    }

    /**
     * @return Generator<int, WebhookEndpoint>
     */
    public function all(?RequestOptions $options = null): Generator
    {
        return $this->list([], $options)->autoPaging();
    }

    /**
     * Creates an endpoint. The answer holds its whsec_ secret once: store it in your secret manager.
     *
     * @param EndpointInput $input url (public https), description and events required
     */
    public function create(array $input, ?RequestOptions $options = null): WebhookEndpointWithSecret
    {
        return $this->write('POST', '/webhooks', $input, WebhookEndpointWithSecret::fromArray(...), $options);
    }

    public function get(int|string $webhookId, ?RequestOptions $options = null): WebhookEndpoint
    {
        return $this->getOne('/webhooks/' . self::id($webhookId, 'webhookId'), WebhookEndpoint::fromArray(...), $options);
    }

    /**
     * enabled: true turns a disabled endpoint back on.
     *
     * @param EndpointInput $input
     */
    public function update(int|string $webhookId, array $input, ?RequestOptions $options = null): WebhookEndpoint
    {
        return $this->write('PATCH', '/webhooks/' . self::id($webhookId, 'webhookId'), $input, WebhookEndpoint::fromArray(...), $options);
    }

    public function delete(int|string $webhookId, ?RequestOptions $options = null): void
    {
        $this->remove('/webhooks/' . self::id($webhookId, 'webhookId'), $options);
    }

    /** Sends a signed ping event now (also to a disabled endpoint). */
    public function test(int|string $webhookId, ?RequestOptions $options = null): WebhookTestResult
    {
        return $this->write('POST', '/webhooks/' . self::id($webhookId, 'webhookId') . '/test', null, WebhookTestResult::fromArray(...), $options);
    }

    /**
     * The delivery log of an endpoint (30 days), newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<WebhookDelivery>
     */
    public function deliveries(int|string $webhookId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/webhooks/' . self::id($webhookId, 'webhookId') . '/deliveries', $params, WebhookDelivery::fromArray(...), $options);
    }
}
