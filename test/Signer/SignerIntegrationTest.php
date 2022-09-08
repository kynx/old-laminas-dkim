<?php

declare(strict_types=1);

namespace KynxTest\Laminas\Dkim\Signer;

use Kynx\Laminas\Dkim\Signer\Params;
use Kynx\Laminas\Dkim\Signer\Signer;
use KynxTest\Laminas\Dkim\PrivateKeyTrait;
use Laminas\Mail\Message;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part;
use PHPMailer\DKIMValidator\Validator;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * @coversNothing
 */
final class SignerIntegrationTest extends TestCase
{
    use PrivateKeyTrait;

    private Message $message;
    private Signer $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new Message();
        $this->message->setEncoding('ASCII')
            ->setFrom('from@example.com')
            ->addTo('to@example.com')
            ->addCc('cc@example.com')
            ->setSubject('Subject Subject')
            ->setBody("Hello world!\r\nHello Again!\r\n");

        $params       = new Params('example.com', '202209', ['From', 'To', 'Subject']);
        $this->signer = new Signer($params, $this->getPrivateKey());
    }

    public function testSignMessageIsValid(): void
    {
        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public function testSignMimeMessageIsValid(): void
    {
        $mime = new MimeMessage();
        $mime->addPart(new Part("Hello world"));
        $this->message->setBody($mime);

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    /**
     * @see https://www.rfc-editor.org/rfc/rfc6376.html#section-5.4
     */
    public function testSignMissingHeaderIsValid(): void
    {
        $params = new Params('example.com', '202209', ['From', 'To', 'Subject', 'Reply-To']);
        $signer = new Signer($params, $this->getPrivateKey());

        $signed = $signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public function testSignMessageNormalisedNewLinesIsValid(): void
    {
        $this->message->setBody("Hello world!\nHello Again!\n");

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public function testSignMessageTrailingNewLinesIsValid(): void
    {
        $this->message->setBody("Hello world!\r\nHello Again!\r\n\r\n");

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public function testSignMessageEmptyBodyIsValid(): void
    {
        $this->message->setBody('');

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    /**
     * @dataProvider headerProvider
     */
    public function testSignMessageCanonicalHeaderIsValid(string $subject): void
    {
        $this->message->setSubject($subject);

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public function headerProvider(): array
    {
        return [
            'internal_whitespace' => ["Subject   Subject"],
            'leading_whitespace'  => ["   Subject Subject"],
            'trailing_whitespace' => ["Subject Subject   "],
        ];
    }

    public function testSignMessageFoldedHeaderIsValid(): void
    {
        // 80-char subject will be wrapped at 70 chars
        $this->message->setSubject(str_repeat("Subject ", 10));

        $signed = $this->signer->signMessage($this->message);
        self::assertSignedMessageIsValid($signed);
    }

    public static function assertSignedMessageIsValid(Message $message): void
    {
        $validator = new Validator($message->toString());
        $actual    = $validator->validateBoolean();
        self::assertTrue($actual);
    }
}
