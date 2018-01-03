# Service Mailer - Client

[![GitHub release](https://img.shields.io/github/release/flash-global/mailer-client.svg?style=for-the-badge)](README.md) 

## Table of contents
- [Purpose](#purpose)
- [Requirements](#requirements)
    - [Runtime](#runtime)
- [Step by step installation](#step-by-step-installation)
    - [Initialization](#initialization)
    - [Settings](#settings)
    - [Known issues](#known-issues)
- [Contribution](#contribution)
- [Link to documentation](#link-to-documentation)
    - [Examples](#examples)



## Purpose
This client permit to use the `Mailer Api`. Thanks to it, you could request the API to :
* Send mails

easily

## Requirements 

### Runtime
- PHP 5.5

## Step by step Installation
> for all purposes (development, contribution and production)

### Initialization
- Cloning repository 
```git clone https://github.com/flash-global/mailer-client.git```
- Run Composer depedencies installation
```composer install```

### Settings

Don't forget to set the right `baseUrl` :

```php
<?php
$logger = new Logger([
    Logger::OPTION_BASEURL => 'http://127.0.0.1:8082',
    Logger::OPTION_FILTER => Notification::LVL_INFO
]);
$logger->setTransport(new BasicTransport());
```

### Known issues
No known issue at this time.

## Contribution
As FEI Service, designed and made by OpCoding. The contribution workflow will involve both technical teams. Feel free to contribute, to improve features and apply patches, but keep in mind to carefully deal with pull request. Merging must be the product of complete discussions between Flash and OpCoding teams :) 

## Link to documentation 

### Examples
You can test this client easily thanks to the folder [example](example)

Here, an example on how to use : `php /my/client-client/folder/examples/client.php` 


Let's start with a simple client :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Mailer\Entity\Mail;

// The client
$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'https://api_host'));
// Optional: a BasicTransport will be instantiate if no synchronous transport was found
$mailer->setTransport(new BasicTransport());

// The mail to send
$message = new Mail();
$message->setSubject('Test subject');
$message->setTextBody('This is a example message');
$message->addRecipient('to@test.com');
$message->setSender(array('sender@test.com'));

// And send !
$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
```

Keep in mind that you should always initialize a mailer client by a dependency injection component, since it requires at
least one dependency, which is the transport. Moreover, the `OPTION_BASEURL` parameter should also depends on environment.

#### Real world example

Below a more robust example which use the `BeanstalkProxyTransport` as default transport.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\ApiClient\Transport\BasicTransport;
use Fei\ApiClient\Transport\BeanstalkProxyTransport;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;
use Pheanstalk\Pheanstalk;

$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'http://api_host'));

$async = new BeanstalkProxyTransport();
$async->setPheanstalk(new Pheanstalk('host'));
// Async transport will be the default transport. 
$mailer->setAsyncTransport($async);
// If async transport fails, then BasicTransport will take over.
$mailer->setTransport(new BasicTransport());

$message = new Mail();
$message->setSubject('Test subject');
$message->setTextBody('This is a example message');
$message->addRecipient('to@test.com');
$message->setSender(array('sender@test.com'));

$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
```

To work properly, `BeanstalkProxyTransport` needs a instance of a Beanstalkd server able to listen to its requests.
Workers will consume Beanstalkd messages tube (or queue) and will send email data to Mail API server.

The message workflow:

```
Client -> Pheanstalkd -> Workers -> Mail API server
```

#### Use the logger

Mailer client is _Logger client aware_. You could set a logger instance like this example below in order to activate logging functionality.

```php
<?php

$logger = new Logger([
    Logger::OPTION_BASEURL => 'http://127.0.0.1:8082',
    Logger::OPTION_FILTER => Notification::LVL_INFO
]);
$logger->setTransport(new BasicTransport());

$mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://127.0.0.1:8081']);
$mailer->setTransport(new BasicTransport());
$mailer->setLogger($logger); // Set and activate the the logger functionality
```

As is each mail sent will be recorded with logger service.

#### Email and attachments

Here a example if you need to send email with attachments :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Mailer\Entity\Mail;
use Fei\Service\Mailer\Entity\Attachment;

$message = new Mail();
$message->setSubject('Test subject');
$message->setTextBody('This is a example message');
$message->setHtmlBody('<p>This is a <strong>example</strong> message!</p>');
$message->setRecipients(array('to@test.com' => 'Name', 'other@test.com' => 'Other Name'));
$message->addCc('cc@example.com', 'CC');
$message->addBcc('bcc@example.com', 'CC');
$message->setSender(array('sender@test.com' => 'The sender'));
$message->setReplyTo(array('steve@app.com' => 'Steve'));

// Add a attachment with a \SplObjectFile
$message->addAttachment(new \SplFileObject('/to/file/path/image.png'));

// Or add a attachment with a "generated string"
$attachment = array(
    'filename' => 'document.txt',
    'mime_type' => 'text/plain',
    // Note base64_encode
    'contents' => base64_encode('Hello world!') . PHP_EOL
);
$message->addAttachment($attachment);

// Or add a attachment object
$attachment = new Attachment('/to/file/path/document.pdf');
$attachment->setAttachmentFilename('another-filename.txt');
$attachment->setMimeType('text/plain');
$message->addAttachment($attachment);

$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'https://api_host'));
$mailer->setTransport(new BasicTransport());
$mailer->transmit($message);
```

#### Embedding attachment

Sometime, you need to include image (or other media) inline in your message. You could use a resource URL in order to
linking the media but this approach is usually blocked by mail clients. A another approach is to embed your media
directly into your message.

```php
<?php

use Fei\Service\Mailer\Entity\Mail;
use Fei\Service\Mailer\Entity\Attachment;

$message = new Mail();

$embedded = (new Attachment('/my/picture.png', true));
$message->addAttachment($embedded);

$message->setTextBody('This is a example message');
$message->setHtmlBody(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Example</title>
</head>
<body>
    <p>
        <img src="{$embedded->getCid()}" style="display: block; width: 100px; height: 100px; float: right;">
        This is example with a embedded image
    </p>
</body>
</html>
HTML
);

```

And that's it.

#### Catch them all

In non production environment, you often don't need to send email to the real recipient. For testing purpose, you could
initialize the client with the option `OPTION_CATCHALL_ADDRESS` and all email will be forwarded to email address passed
to this option.

```php
<?php

use Fei\Service\Mailer\Client\Mailer;

$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'http://127.0.0.1:8081',
    Mailer::OPTION_CATCHALL_ADDRESS => 'testing@email.com'
]);

```

#### Add callback functions before Mail instance validation

For different needs, you could registered a callback to apply on `Mail` to be send before its state validation.

You can see this as a another way to extends the "mailer-client" functionalities. 

```php
<?php

use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;

$mailer = new Mailer();
$mailer->addCallbackBeforeValidation(function (Mail $mail) {
    $mail->setRecipients(['another@email.com']);
});

```

With this example, all `Mail` send with this client will have theirs recipients changed by `another@email.com`.

We provide a couple of another method to manage callbacks:

* `Mailer::addFirstCallbackBeforeValidation`: append a callback on the first place of the stack to be executed in
  contrast to `addCallbackBeforeValidation` which place the callback in the end of the chain execution. 
* `Mailer::clearCallbackBeforeValidation`: remove all callback registered in the stack 
