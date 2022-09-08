<?php

declare(strict_types=1);

namespace KynxTest\Laminas\Dkim\Signer;

use Kynx\Laminas\Dkim\Exception\InvalidParamException;
use Kynx\Laminas\Dkim\Signer\Params;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kynx\Laminas\Dkim\Signer\Params
 */
final class ParamsTest extends TestCase
{
    public function testConstructorSetsParams(): void
    {
        $domain   = 'example.com';
        $selector = 'sel1';
        $headers  = ['date', 'from', 'subject'];

        $params = new Params($domain, $selector, $headers);
        self::assertSame($domain, $params->getDomain());
        self::assertSame($selector, $params->getSelector());
        self::assertSame($headers, $params->getHeaders());
    }

    public function testConstructorSetsDefaults(): void
    {
        $expectedHeaders = ['date', 'from', 'reply-to', 'sender', 'subject'];

        $params = new Params('example.com', 'sel1');
        self::assertSame($expectedHeaders, $params->getHeaders());
        self::assertSame(1, $params->getVersion());
        self::assertSame('relaxed/simple', $params->getCanonicalization());
        self::assertNull($params->getIdentifier());
    }

    public function testConstructorAddsFromToHeaders(): void
    {
        $params = new Params('example.com', 'sel1', []);
        self::assertSame(['from'], $params->getHeaders());
    }

    public function testConstructorEmptyDomainThrowsException(): void
    {
        self::expectException(InvalidParamException::class);
        self::expectExceptionMessage("Domain cannot be empty");
        new Params('', 'sel1');
    }

    public function testConstructorEmptySelectorThrowsException(): void
    {
        self::expectException(InvalidParamException::class);
        self::expectExceptionMessage("Selector cannot be empty");
        new Params('example.com', '');
    }
}
