# Mailer client

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

### Use the logger Luke

Mailer client is _Logger client aware_. You could set a logger instance like this example below in order to activate logging functionality.

```php
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

## Email and attachments

Here a example if you need to send email with attachments :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Mailer\Entity\Mail;

$message = new Mail();
$message->setSubject('Test subject');
$message->setTextBody('This is a example message');
$message->setHtmlBody('<p>This is a <strong>example</strong> message!</p>');
$message->setRecipients(array('to@test.com' => 'Name', 'other@test.com' => 'Other Name'));
$message->addCc('cc@example.com', 'CC');
$message->addBcc('bcc@example.com', 'CC');
$message->setSender(array('sender@test.com' => 'The sender'));

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

$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'https://api_host'));
$mailer->setTransport(new BasicTransport());
$mailer->transmit($message);
```

## Client option

Only one option is available which can be passed to the `__construct()` or `setOptions()` methods:

| Option                  | Description                                    | Type   | Possible Values                                | Default |
|-------------------------|------------------------------------------------|--------|------------------------------------------------|---------|
| OPTION_BASEURL          | This is the server to which send the requests. | string | Any URL, including protocol but excluding path | -       |
| OPTION_CATCHALL_ADDRESS | Recipient to substitute to any other. When used, original recipients, ccs, bccs are prepended to mail body.| string | Any valid email address | - |