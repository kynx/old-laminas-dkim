<?php

declare(strict_types=1);

namespace KynxTest\Laminas\Dkim\Signer;

use Exception;
use Kynx\Laminas\Dkim\Signer\Signer;
use Kynx\Laminas\Dkim\Signer\SignerFactory;
use KynxTest\Laminas\Dkim\PrivateKeyTrait;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @uses \Kynx\Laminas\Dkim\Signer\Signer
 * @uses \Kynx\Laminas\Dkim\PrivateKey\RsaSha256
 * @uses \Kynx\Laminas\Dkim\Signer\Params
 *
 * @covers \Kynx\Laminas\Dkim\Signer\SignerFactory
 */
final class SignerFactoryTest extends TestCase
{
    use PrivateKeyTrait;

    public function testInvokeMissingConfigThrowsException(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([]);

        $factory = new SignerFactory();
        self::expectException(Exception::class);
        self::expectExceptionMessage("No 'dkim' config set");
        $factory($container);
    }

    public function testInvokeMissingParamsThrowsException(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'dkim' => [],
            ]);

        $factory = new SignerFactory();
        self::expectException(Exception::class);
        self::expectExceptionMessage("No dkim params config set");
        $factory($container);
    }

    public function testInvokeMissingPrivateKeyThrowsException(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'dkim' => ['params' => []],
            ]);

        $factory = new SignerFactory();
        self::expectException(Exception::class);
        self::expectExceptionMessage("No dkim private key set");
        $factory($container);
    }

    public function testInvokeReturnsInstance(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'dkim' => [
                    'private_key' => $this->getPrivateKeyString(),
                    'params'      => [
                        'domain'   => 'example.com',
                        'selector' => 'k1',
                        'headers'  => [],
                    ],
                ],
            ]);

        $factory = new SignerFactory();
        $actual  = $factory($container);
        self::assertInstanceOf(Signer::class, $actual);
    }
}
