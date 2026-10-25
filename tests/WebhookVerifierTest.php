<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use InvalidArgumentException;
use Kodvalley\Improj\Webhook\SignatureVerificationException;
use Kodvalley\Improj\Webhook\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'whsec_TEST_ONLY_not_a_real_secret';
    private const NOW = 1_793_000_000;
    private const BODY = '{"id":"evt_12","type":"task.updated","api_version":"2026-10-24","created_at":"2026-10-24T09:30:00.000Z","delivery_id":34,"data":{"object":"task","id":56,"project_id":7},"actor_user_id":8}';

    /** The server's algorithm, written independently. */
    private static function header(string $secret, string $body, int $t): string
    {
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }

    public function testAcceptsADeliverySignedLikeTheServerSignsIt(): void
    {
        $v = new WebhookVerifier(self::SECRET);
        $event = $v->constructEvent(self::BODY, self::header(self::SECRET, self::BODY, self::NOW - 10), self::NOW);
        self::assertSame('evt_12', $event->id);
        self::assertSame('task.updated', $event->type);
        self::assertSame(34, $event->deliveryId);
        self::assertSame('task', $event->data->object);
        self::assertSame(56, $event->data->id);
        self::assertSame(7, $event->data->projectId);
        self::assertSame(8, $event->actorUserId);
        self::assertTrue($v->isValid(self::BODY, $v->signatureHeader(self::BODY)));
        self::assertSame(self::header(self::SECRET, self::BODY, self::NOW), $v->signatureHeader(self::BODY, self::NOW));
    }

    public function testMemberEventsCarryAStringId(): void
    {
        $body = '{"id":"evt_1","type":"member.added","api_version":"2026-10-24","created_at":"2026-10-24T09:30:00.000Z","delivery_id":1,"data":{"object":"member","id":"m_5","project_id":7},"actor_user_id":null}';
        $event = (new WebhookVerifier(self::SECRET))->constructEvent($body, self::header(self::SECRET, $body, self::NOW), self::NOW);
        self::assertSame('m_5', $event->data->id);
        self::assertNull($event->actorUserId);
    }

    public function testRefusesAWrongSecretAChangedBodyAndAReencodedBody(): void
    {
        $v = new WebhookVerifier(self::SECRET);
        $header = self::header(self::SECRET, self::BODY, self::NOW);
        self::assertFalse((new WebhookVerifier('whsec_TEST_ONLY_other_secret'))->isValid(self::BODY, $header, self::NOW));
        self::assertFalse($v->isValid(str_replace('56', '57', self::BODY), $header, self::NOW));
        self::assertFalse($v->isValid((string) json_encode(json_decode(self::BODY, true), \JSON_PRETTY_PRINT), $header, self::NOW));
    }

    public function testTimestampToleranceBothWays(): void
    {
        $v = new WebhookVerifier(self::SECRET);
        self::assertFalse($v->isValid(self::BODY, self::header(self::SECRET, self::BODY, self::NOW - 301), self::NOW));
        self::assertFalse($v->isValid(self::BODY, self::header(self::SECRET, self::BODY, self::NOW + 301), self::NOW));
        self::assertTrue($v->isValid(self::BODY, self::header(self::SECRET, self::BODY, self::NOW - 300), self::NOW));
        self::assertTrue((new WebhookVerifier(self::SECRET, 1000))->isValid(self::BODY, self::header(self::SECRET, self::BODY, self::NOW - 900), self::NOW));
        try {
            $v->verify(self::BODY, self::header(self::SECRET, self::BODY, self::NOW - 301), self::NOW);
            self::fail('accepted');
        } catch (SignatureVerificationException $e) {
            self::assertSame(SignatureVerificationException::REASON_TIMESTAMP_OUTSIDE_TOLERANCE, $e->getReason());
            self::assertSame('WEBHOOK_SIGNATURE_INVALID', $e->getErrorCode());
        }
    }

    public function testAcceptsAnyOfSeveralSignatures(): void
    {
        $good = explode(',', self::header(self::SECRET, self::BODY, self::NOW))[1];
        $old = explode(',', self::header('whsec_TEST_ONLY_old_secret', self::BODY, self::NOW))[1];
        $v = new WebhookVerifier(self::SECRET);
        self::assertTrue($v->isValid(self::BODY, 't=' . self::NOW . ",{$old},{$good}", self::NOW));
        self::assertTrue($v->isValid(self::BODY, ' t=' . self::NOW . ' , v0=abc, v1=' . strtoupper(substr($good, 3)) . ',junk', self::NOW));
        self::assertFalse($v->isValid(self::BODY, 't=' . self::NOW . ",{$old}", self::NOW));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function badHeaders(): iterable
    {
        yield 'missing' => [null, SignatureVerificationException::REASON_MISSING_HEADER];
        yield 'empty' => ['', SignatureVerificationException::REASON_MISSING_HEADER];
        yield 'no timestamp' => ['v1=' . str_repeat('a', 64), SignatureVerificationException::REASON_MALFORMED_HEADER];
        yield 'no signature' => ['t=' . self::NOW, SignatureVerificationException::REASON_MALFORMED_HEADER];
        yield 'short signature' => ['t=' . self::NOW . ',v1=abc', SignatureVerificationException::REASON_MALFORMED_HEADER];
        yield 'text timestamp' => ['t=abc,v1=' . str_repeat('a', 64), SignatureVerificationException::REASON_MALFORMED_HEADER];
        yield 'too long' => ['t=1,v1=' . str_repeat('a', 5000), SignatureVerificationException::REASON_MALFORMED_HEADER];
    }

    #[DataProvider('badHeaders')]
    public function testReportsWhyAHeaderFails(?string $header, string $reason): void
    {
        $v = new WebhookVerifier(self::SECRET);
        self::assertFalse($v->isValid(self::BODY, $header, self::NOW));
        try {
            $v->constructEvent(self::BODY, $header, self::NOW);
            self::fail('accepted');
        } catch (SignatureVerificationException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    public function testKeepsAtMostTenSignatures(): void
    {
        $many = implode(',', array_fill(0, 20, 'v1=' . str_repeat('a', 64)));
        self::assertCount(10, WebhookVerifier::parseHeader('t=1,' . $many)[1]);
    }

    public function testRefusesAVerifiedBodyThatIsNotAnEvent(): void
    {
        $v = new WebhookVerifier(self::SECRET);
        foreach (['not json', '[1,2]', '{"hello":1}'] as $body) {
            try {
                $v->constructEvent($body, self::header(self::SECRET, $body, self::NOW), self::NOW);
                self::fail('accepted');
            } catch (SignatureVerificationException $e) {
                self::assertSame(SignatureVerificationException::REASON_INVALID_PAYLOAD, $e->getReason());
            }
        }
    }

    public function testValidatesItsOptionsAndHidesTheSecret(): void
    {
        self::assertStringNotContainsString('not_a_real_secret', print_r(new WebhookVerifier(self::SECRET), true));
        $this->expectException(InvalidArgumentException::class);
        new WebhookVerifier(self::SECRET, 0);
    }

    public function testRefusesAMissingSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WebhookVerifier('');
    }
}
