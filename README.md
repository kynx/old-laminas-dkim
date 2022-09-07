# kynx/laminas-dkim

[laminas-mail] DKIM Signer.

This is a drop in replacement for [metalinspired/laminas-dkim], which the code is entirely based on. I've added unit 
tests and bug fixes to this branch. That package was forked from [joepsyko/zf-dkim], which in turn was forked from 
[fastnloud/zf-dkim].

If you are looking for PHP8+ support, more improvements, or would like to contribute, please try the 2.x branch.

## Installation

```
composer require kynx/laminas-dkim
```

If you are adding this to an existing Laminas or Mezzio project, you should be prompted to add the package as a module 
/ to your config. 

Next copy the configuration to your autoload directory:

```
cp vendor/kynx/laminas-dkim/config/dkim.global.php.dist config/autoload/dkim.global.php
cp vendor/kynx/laminas-dkim/config/dkim.local.php.dist config/autoload/dkim.local.php
```

This `dkim.local.php` will contain the private key used to sign messages: **do not** check it into version control!

Now create a private signing key, as described at [dkimcore.org], and add it to the `dkim.local.php` file you copied above, 
_without_ the surrounding `-----BEGIN RSA PRIVATE KEY-----` / `-----END RSA PRIVATE KEY-----`. 

Finish the configuration by setting your domain, selector and the headers you want to sign in the `dkim.global.php` 
file.

You should now be able to sign messages. But you still will need to configure your DNS `TXT` record before receiving 
mail servers will be able to verify it: see [dkimcore.org] for more instructions.

## Usage

```php
<?php 

require 'vendor/autoload.php';

$container = require 'config/container.php';

$mail = new \Laminas\Mail\Message();
$mail->setBody("Hello world!");
$mail->setFrom('from@example.com');
$mail->addTo('to@example.com');
$mail->setSubject('subject');

// Sign message with dkim
$signer = $container->get(\Dkim\Signer\Signer::class);
$signer->signMessage($mail);

// Send message
$transport = new \Laminas\Mail\Transport\Sendmail();
$transport->send($mail);
```

[laminas-mail]: https://docs.laminas.dev/laminas-mail/
[metalinspired/laminas-dkim]: https://github.com/metalinspired/laminas-dkim
[joepsyko/zf-dkim]: https://github.com/joepsyko/zf-dkim
[fastnloud/zf-dkim]: https://github.com/fastnloud/zf-dkim
[dkimcore.org]: http://dkimcore.org/specification.html
