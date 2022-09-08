<?php

namespace Kynx\Laminas\Dkim\Signer;

use Exception;
use Kynx\Laminas\Dkim\PrivateKey\RsaSha256;
use Psr\Container\ContainerInterface;

use function array_merge;

/**
 * @see \KynxTest\Laminas\Dkim\Signer\SignerFactoryTest
 */
final class SignerFactory
{
    public function __invoke(ContainerInterface $container): Signer
    {
        $config = $container->get('config');
        assert(is_array($config));

        if (! (isset($config['dkim']) && is_array($config['dkim']))) {
            throw new Exception("No 'dkim' config set");
        }
        $dkim = $config['dkim'];

        if (! (isset($dkim['params']) && is_array($dkim['params']))) {
            throw new Exception("No dkim params config set");
        }

        if (! (isset($dkim['private_key']) && is_string($dkim['private_key'])) || $dkim['private_key'] === '') {
            throw new Exception("No dkim private key set");
        }

        /** @var array{domain: string, selector: string, headers: list<string>} $params */
        $params = $dkim['params'];

        return new Signer(
            new Params($params['domain'], $params['selector'], $params['headers']),
            new RsaSha256($dkim['private_key'])
        );
    }
}
