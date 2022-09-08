<?php

namespace Kynx\Laminas\Dkim\Signer;

use Kynx\Laminas\Dkim\Exception\ExceptionInterface;
use Kynx\Laminas\Dkim\Exception\InvalidParamException;
use Kynx\Laminas\Dkim\Exception\InvalidPrivateKeyException;
use Kynx\Laminas\Dkim\Exception\MissingParamException;
use Kynx\Laminas\Dkim\Header\Dkim;
use Laminas\Mail\Header;
use Laminas\Mail\Message;
use Laminas\Mime\Message as MimeMessage;
use OpenSSLAsymmetricKey;

use function array_key_exists;
use function base64_encode;
use function chunk_split;
use function explode;
use function hash;
use function in_array;
use function is_array;
use function is_resource;
use function openssl_pkey_get_private;
use function openssl_sign;
use function pack;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

use const OPENSSL_ALGO_SHA256;

/**
 * @see \KynxTest\Laminas\Dkim\Signer\SignerTest
 */
final class Signer
{
    /**
     * All configurable params.
     */
    private array $params = [
        // optional params having a default value set
        'v' => '1',
        'a' => 'rsa-sha256',
        // required to set either in your config file or through the setParam method before signing (see
        // module.config.dist file)
        'd' => '', // domain
        'h' => '', // headers to sign
        's' => '', // domain key selector
    ];

    /**
     * The private key being used.
     *
     * @var bool|resource|OpenSSLAsymmetricKey key
     */
    private $privateKey = false;

    /**
     * Set and validate DKIM options.
     *
     * @throws ExceptionInterface
     */
    public function __construct(array $config)
    {
        if (isset($config['private_key']) && ! empty($config['private_key'])) {
            $this->setPrivateKey($config['private_key']);
        }

        if (isset($config['params']) && is_array($config['params']) && ! empty($config['params'])) {
            foreach ($config['params'] as $key => $value) {
                $this->setParam($key, $value);
            }
        }
    }

    /**
     * Returns signed message with a DKIM signature.
     */
    public function signMessage(Message $message): Message
    {
        $clone     = $this->cloneMessage($message);
        $formatted = $this->formatMessage($clone);
        $dkim      = $this->getEmptyDkimHeader($formatted);

        // add empty (unsigned) dkim header
        $formatted->getHeaders()->addHeader($dkim);

        $canonical = $this->getCanonicalHeaders($formatted);
        return $this->sign($formatted, $dkim, $canonical);
    }

    /**
     * Set Dkim param.
     *
     * @throws InvalidParamException
     */
    public function setParam(string $key, string $value): self
    {
        if (! array_key_exists($key, $this->getParams())) {
            throw new InvalidParamException("Invalid param '$key' given.");
        }

        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Set multiple Dkim params.
     */
    public function setParams(array $params): self
    {
        if (! empty($params)) {
            foreach ($params as $key => $value) {
                $this->setParam($key, $value);
            }
        }

        return $this;
    }

    /**
     * Set (generate) OpenSSL key.
     *
     * @throws InvalidPrivateKeyException
     */
    public function setPrivateKey(string $privateKey): self
    {
        $key = <<<PKEY
-----BEGIN RSA PRIVATE KEY-----
$privateKey
-----END RSA PRIVATE KEY-----
PKEY;

        $key = @openssl_pkey_get_private($key);

        if (! $key) {
            throw new InvalidPrivateKeyException("Invalid private key given.");
        }

        $this->privateKey = $key;

        return $this;
    }

    /**
     * Returns deap clone of message
     */
    private function cloneMessage(Message $message): Message
    {
        $clone = clone $message;
        $clone->setHeaders(clone $message->getHeaders());
        $body = $message->getBody();
        if ($body instanceof MimeMessage) {
            $clone->setBody(clone $body);
        }

        return $clone;
    }

    /**
     * Format message for singing.
     */
    private function formatMessage(Message $message): Message
    {
        $body = $message->getBody();

        if ($body instanceof MimeMessage) {
            $body = $body->generateMessage();
        }

        $body = $this->normalizeNewlines($body);

        $message->setBody($body);

        return $message;
    }

    /**
     * Normalize new lines to CRLF sequences.
     */
    private function normalizeNewlines(string $string): string
    {
        return trim(preg_replace('~\R~u', "\r\n", $string)) . "\r\n";
    }

    /**
     * Returns canonical headers for signing.
     */
    private function getCanonicalHeaders(Message $message): string
    {
        $canonical     = '';
        $params        = $this->getParams();
        $headersToSign = explode(':', $params['h']);

        if (! in_array('dkim-signature', $headersToSign, true)) {
            $headersToSign[] = 'dkim-signature';
        }

        foreach ($headersToSign as $fieldName) {
            $fieldName = strtolower($fieldName);
            $header    = $message->getHeaders()->get($fieldName);

            if ($header instanceof Header\HeaderInterface) {
                $canonical .= $fieldName . ':' . trim(preg_replace(
                    '/\s+/',
                    ' ',
                    $header->getFieldValue(Header\HeaderInterface::FORMAT_ENCODED)
                )) . "\r\n";
            }
        }

        return trim($canonical);
    }

    /**
     * Returns empty DKIM header.
     *
     * @throws MissingParamException
     */
    private function getEmptyDkimHeader(Message $message): Dkim
    {
        // fetch configurable params
        $configurableParams = $this->getParams();

        // check if the required params are set for singing.
        if (empty($configurableParams['d']) || empty($configurableParams['h']) || empty($configurableParams['s'])) {
            throw new MissingParamException('Unable to sign message: missing params');
        }

        // final params
        $params = [
            'v'  => $configurableParams['v'],
            'a'  => $configurableParams['a'],
            'bh' => $this->getBodyHash($message),
            'c'  => 'relaxed/simple',
            'd'  => $configurableParams['d'],
            'h'  => $configurableParams['h'],
            's'  => $configurableParams['s'],
            'b'  => '',
        ];

        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '; ';
        }

        return new Dkim(substr(trim($string), 0, -1));
    }

    /**
     * Generate signature.
     *
     * @throws InvalidPrivateKeyException
     */
    private function generateSignature(string $canonicalHeaders): string
    {
        $privateKey = $this->getPrivateKey();
        if (! (is_resource($privateKey) || $privateKey instanceof OpenSSLAsymmetricKey)) {
            throw new InvalidPrivateKeyException('No private key given.');
        }

        $signature = '';
        /** @psalm-suppress PossiblyInvalidArgument This can be removed once php7.4 support is dropped */
        openssl_sign($canonicalHeaders, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return trim(chunk_split(base64_encode($signature), 73, ' '));
    }

    /**
     * Sign message.
     */
    private function sign(Message $message, Dkim $emptyDkimHeader, string $canonicalHeaders): Message
    {
        // generate signature
        $signature = $this->generateSignature($canonicalHeaders);

        $headers = $message->getHeaders();

        // first remove the empty dkim header
        $headers->removeHeader('DKIM-Signature');

        // generate new header set starting with the dkim header
        $headerSet[] = new Dkim($emptyDkimHeader->getFieldValue() . $signature);

        // then append existing headers
        foreach ($headers as $header) {
            $headerSet[] = $header;
        }

        $headers
            // clear headers
            ->clearHeaders()
            // add the newly created header set with the dkim signature
            ->addHeaders($headerSet);

        return $message;
    }

    /**
     * Get configurable params.
     */
    private function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get Message body (sha256) hash.
     */
    private function getBodyHash(Message $message): string
    {
        return base64_encode(pack("H*", hash('sha256', $message->getBody())));
    }

    /**
     * Return OpenSSL key resource.
     *
     * @return bool|resource|OpenSSLAsymmetricKey
     */
    private function getPrivateKey()
    {
        return $this->privateKey;
    }
}
