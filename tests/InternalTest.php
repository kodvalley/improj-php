<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Internal\Data;
use Kodvalley\Improj\Internal\Redactor;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class InternalTest extends TestCase
{
    public function testRedactsTokensSecretsAndBearerCredentials(): void
    {
        $pat = implode('_', ['improj', 'pat', str_repeat('Zz9', 16) . 'X']);
        $org = implode('_', ['improj', 'org', str_repeat('Qq1', 16) . 'Y']);
        $text = "a {$pat} b {$org} c whsec_abc-DEF_123 d imp_" . str_repeat('a1', 20) . ' e Authorization: Bearer xyz.123';
        self::assertSame('a improj_pat_*** b improj_org_*** c whsec_*** d imp_*** e Authorization: Bearer ***', Redactor::redact($text));
        self::assertSame('Task 12 was updated', Redactor::redact('Task 12 was updated'));
        self::assertSame('(none)', Redactor::maskToken(null));
        self::assertSame('improj_pat_***' . substr($pat, -4), Redactor::maskToken($pat));
        self::assertSame('***', Redactor::maskToken('short'));
        self::assertStringNotContainsString($pat, (new ImprojException("failed with {$pat}", 'X'))->getMessage());
    }

    public function testDataRefusesWrongTypes(): void
    {
        $d = ['i' => 1, 's' => 'x', 'b' => true, 'l' => [1, 'a'], 'o' => ['k' => 1], 'n' => null, 'strs' => ['a', 1], 'ints' => [1, 'x'], 'objs' => [1]];
        $cases = [
            static fn() => Data::int($d, 's'),
            static fn() => Data::intOrNull($d, 's'),
            static fn() => Data::string($d, 'i'),
            static fn() => Data::stringOrNull($d, 'i'),
            static fn() => Data::bool($d, 'i'),
            static fn() => Data::object($d, 'l'),
            static fn() => Data::list($d, 'o'),
            static fn() => Data::strings($d, 'strs'),
            static fn() => Data::intsOrNull($d, 'ints'),
            static fn() => Data::objects($d, 'objs'),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('accepted');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertNull(Data::objectOrNull($d, 'n'));
        self::assertNull(Data::intsOrNull($d, 'missing'));
        self::assertSame(['k' => 1], Data::object($d, 'o'));
        self::assertSame([], Data::object(['e' => []], 'e'));
    }
}
