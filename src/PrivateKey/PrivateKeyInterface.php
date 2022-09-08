<?php

declare(strict_types=1);

namespace Kynx\Laminas\Dkim\PrivateKey;

interface PrivateKeyInterface
{
    /**
     * Returns base64 encoded signature for payload
     */
    public function createSignature(string $payload): string;

    /**
     * Returns algorithm name for use in DKIM signature (ie 'rsa-sha256')
     */
    public function getAlgorithm(): string;
}
