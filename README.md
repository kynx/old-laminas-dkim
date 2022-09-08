# kynx/laminas-dkim

Add DKIM signatures [laminas-mail] messages.

This is an evolution of [metalinspired/laminas-dkim], with improvements, bug fixes, tests and modernised code. That 
package was forked from [joepsyko/zf-dkim], which in turn was forked from [fastnloud/zf-dkim].


## Installation

```
composer require kynx/laminas-dkim
```

If you are adding this to an existing Laminas or Mezzio project, you should be prompted to add the package as a module 
/ to your `config/config.php`. 

Next copy the configuration to your autoload directory:

```
cp vendor/kynx/laminas-dkim/config/dkim.global.php.dist config/autoload/dkim.global.php
cp vendor/kynx/laminas-dkim/config/dkim.local.php.dist config/autoload/dkim.local.php
```

The `dkim.local.php` file will contain the private key used to sign messages: **do not** check it into version control!

Create a private signing key, as described at [dkimcore.org], and add it to the `dkim.local.php` file you copied 
above, _without_ the surrounding `-----BEGIN RSA PRIVATE KEY-----` / `-----END RSA PRIVATE KEY-----`. 

Finish the configuration by setting your `domain`, `selector` and the `headers` you want to sign in `dkim.global.php`.

You will now be able to sign messages. But you still will need to configure your DNS `TXT` record before receiving mail 
servers will be able to verify it: see [dkimcore.org] for details on the format for that.

## Usage

### Manual instantiation

```php
<?php 

require 'vendor/autoload.php';

use Kynx\Laminas\Dkim\PrivateKey\RsaSha256;
use Kynx\Laminas\Dkim\Signer\Params;
use Kynx\Laminas\Dkim\Signer\Signer;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Sendmail;

$mail = Message();
$mail->setBody("Hello world!")
    ->setFrom('from@example.com')
    ->addTo('to@example.com')
    ->setSubject('subject');

// Create signer
$privateKey = new RsaSha256('your private key');
$params = new Params('example.com', 'sel1', ['From', 'To', 'Subject']);
$signer = new Signer($params, $privateKey);

// Sign message
$signed = $signer->signMessage($mail);

// Send message
$transport = new Sendmail();
$transport->send($signed);
```

### Factory based instantiation

```php
<?php 

require 'vendor/autoload.php';

use Kynx\Laminas\Dkim\Signer\Signer;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;

// Get container (Mezzio example)
$container = require 'config/container.php';

$mail = Message();
$mail->setBody("Hello world!")
    ->setFrom('from@example.com')
    ->addTo('to@example.com')
    ->setSubject('subject');

// Get configured Signer
$signer = $container->get(Signer::class);

// Sign message
$signed = $signer->signMessage($mail);

// Send message
$transport = $container->get(TransportInterface::class);
$transport->send($signed);
```

## Upgrading

The API has undergone a number of changes since version 1.x:

* All classes are now under the `Kynx\Laminas\Dkim` namespace. It seemed a bit rude to hog the top-level `Dkim` 
  namespace, and could cause conflicts with other DKIM-related packages. 
* `Signer` is now stateless. This fixes problems with signing multiple messages and permits usage in long-running
  processes such as mezzio-swoole.
* `Signer` now consumes a `Params` instance and a `PrivateKeyInterface`. This provides a more friendly interface to 
  DKIM's options, and will permit other signing algorithms in future.
* `Signer::signMessage()` now _returns_ the signed message, leaving the original unaltered.
* The configuration files now use human-readable keys for parameters, instead of `d`, `s` and `h`.

### To upgrade:

* Search-and-replace `use Dkim\` with `use Kynx\Laminas\Dkim\`
* Update the parameters in your configuration files to use `domain`, `selector` and `headers` instead of `d`, `s` and 
  `h`. See [dkim.global.php.dist] for an example.
* Change your code to use the signed message returned from `Signer::signMessage()`:

Before (1.x):
```php
<?php

$signer->signMessage($message);
```

After (2.x):
```php
<?php

$message = $signer->signMessage($message);
```

If you are manually constructing the `Signer` instance, see the Manual Instatiation section above for the new structure.

[laminas-mail]: https://docs.laminas.dev/laminas-mail/
[metalinspired/laminas-dkim]: https://github.com/metalinspired/laminas-dkim
[joepsyko/zf-dkim]: https://github.com/joepsyko/zf-dkim
[fastnloud/zf-dkim]: https://github.com/fastnloud/zf-dkim
[dkimcore.org]: http://dkimcore.org/specification.html
[dkim.global.php.dist]: ./config/dkim.global.php.dist