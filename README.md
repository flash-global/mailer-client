# Mailer client
[![GitHub license](https://img.shields.io/github/license/flash-global/mailer-client.svg)](https://github.com/flash-global/mailer-client)![continuousphp](https://img.shields.io/continuousphp/git-hub/flash-global/mailer-client.svg)[![GitHub issues](https://img.shields.io/github/issues/flash-global/mailer-client.svg)](https://github.com/flash-global/mailer-client/issues)

This is the client you should use to send email to Mail Api.

The client can use two kind of transports to send emails:

* Asynchronous transport implemented by `BeanstalkProxyTransport`
* Synchronous transport implemented by `BasicTransport`

`BeanstalkProxyTransport` delegate the API consumption to workers by sending email properties to a Beanstalkd queue.

`BasicTransport` use the _classic_ HTTP layer to send emails.

If asynchronous transport is set, it will act as default transport. Synchronous transport will be a fallback in case when asynchronous transport fails.

## Installation

Add this requirement to your `composer.json`: `"fei/mailer-client": : "^1.0.0"`

Or execute `composer.phar require fei/mailer-client` in your terminal.

## Quick start

Let's start with a simple client :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Mailer\Entity\Mail;

// The client
$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'https://api_host',
    Mailer::OPTION_HEADER_AUTHORIZATION => 'key'
]);
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

## Real world example

Below a more robust example which use the `BeanstalkProxyTransport` as default transport.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\ApiClient\Transport\BasicTransport;
use Fei\ApiClient\Transport\BeanstalkProxyTransport;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;
use Pheanstalk\Pheanstalk;

$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'https://api_host',
    Mailer::OPTION_HEADER_AUTHORIZATION => 'key'
]);

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

### Use the logger

Mailer client is _Logger client aware_. You could set a logger instance like this example below in order to activate logging functionality.

```php
<?php

$logger = new Logger([
    Logger::OPTION_BASEURL => 'http://127.0.0.1:8082',
    Logger::OPTION_FILTER => Notification::LVL_INFO,
    Logger::OPTION_HEADER_AUTHORIZATION => 'key'
]);
$logger->setTransport(new BasicTransport());

$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'https://api_host',
    Mailer::OPTION_HEADER_AUTHORIZATION => 'key'
]);
$mailer->setTransport(new BasicTransport());
$mailer->setLogger($logger); // Set and activate the the logger functionality
```

As is each mail sent will be recorded with logger service.

## Email and attachments

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

$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'https://api_host',
    Mailer::OPTION_HEADER_AUTHORIZATION => 'key'
]);
$mailer->setTransport(new BasicTransport());
$mailer->transmit($message);
```

### Embedding attachment

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

## Catch them all

In non production environment, you often don't need to send email to the real recipient. For testing purpose, you could
initialize the client with the option `OPTION_CATCHALL_ADDRESS` and all email will be forwarded to email address passed
to this option.

```php
<?php

use Fei\Service\Mailer\Client\Mailer;

$mailer = new Mailer([
    Mailer::OPTION_BASEURL => 'https://api_host',
    Mailer::OPTION_CATCHALL_ADDRESS => 'testing@email.com',
    Mailer::OPTION_HEADER_AUTHORIZATION => 'key'
]);

```

## Add callback functions before Mail instance validation

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

## Pricer integration: meet the `PricerMailer` class

We provide a child class of `Mailer` for the Pricer need: `PricerMailer`.

`PricerMailer` extends functionalities `Mailer` plus a callback which apply email filtering on the `Mail` instance to
be send. Naturally, you can remove this filter or add yours own.

## Client option

Only one option is available which can be passed to the `__construct()` or `setOptions()` methods:

| Option                  | Description                                    | Type   | Possible Values                                | Default |
|-------------------------|------------------------------------------------|--------|------------------------------------------------|---------|
| OPTION_BASEURL          | This is the server to which send the requests. | string | Any URL, including protocol but excluding path | -       |
| OPTION_CATCHALL_ADDRESS | Recipient to substitute to any other. When used, original recipients, ccs, bccs are prepended to mail body.| string | Any valid email address | - |
| OPTION_HEADER_AUTHORIZATION    | Api Key for authentification   | string | Any string value                               | ''      |
