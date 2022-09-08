<?php

declare(strict_types=1);

namespace Kynx\Laminas\Dkim\Signer;

use Kynx\Laminas\Dkim\Exception\InvalidParamException;

use function array_map;
use function in_array;

/**
 * @see https://www.rfc-editor.org/rfc/rfc6376#section-3.5
 * @see \KynxTest\Laminas\Dkim\Signer\ParamsTest
 */
final class Params
{
    private const DEFAULT_HEADERS = ['Date', 'From', 'Reply-To', 'Sender', 'Subject'];

    private int $version;
    private string $domain;
    private string $selector;
    /** @var list<string> */
    private array $headers;
    private ?string $identifier;
    private string $canonicalization;

    /**
     * @param list<string> $headers
     */
    public function __construct(string $domain, string $selector, array $headers = self::DEFAULT_HEADERS)
    {
        if ($domain === '') {
            throw new InvalidParamException("Domain cannot be empty");
        }
        if ($selector === '') {
            throw new InvalidParamException("Selector cannot be empty");
        }

        $headers = array_map('strtolower', $headers);
        if (! in_array('from', $headers, true)) {
            $headers[] = 'from';
        }

        $this->domain   = $domain;
        $this->selector = $selector;
        $this->headers  = $headers;

        $this->version          = 1;
        $this->identifier       = null;
        $this->canonicalization = 'relaxed/simple';
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    /**
     * @return list<string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getCanonicalization(): string
    {
        return $this->canonicalization;
    }
}
