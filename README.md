#Mailer client

Simple example :

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fei\ApiClient\Transport\BasicTransport;
use Fei\ApiClient\Transport\BeanstalkProxyTransport;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;
use Pheanstalk\Pheanstalk;

$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'http://192.168.0.198:8081'));
$proxy = new BeanstalkProxyTransport();
$proxy->setPheanstalk(new Pheanstalk('127.0.0.1'));
$mailer->setAsyncTransport($proxy);
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
