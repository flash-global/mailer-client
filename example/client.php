<?php

require __DIR__ . '/../vendor/autoload.php';

use Fei\ApiClient\Transport\BasicTransport;
use Fei\ApiClient\Transport\BeanstalkProxyTransport;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Logger\Entity\Notification;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;
use Pheanstalk\Pheanstalk;

$logger = new Logger([
    Logger::OPTION_BASEURL => 'http://127.0.0.1:8082',
    Logger::OPTION_FILTER => Notification::LVL_INFO
]);
$logger->setTransport(new BasicTransport());

$mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://127.0.0.1:8081']);
$proxy = new BeanstalkProxyTransport();
$proxy->setPheanstalk(new Pheanstalk('127.0.0.1'));
$mailer->setAsyncTransport($proxy);
$mailer->setTransport(new BasicTransport());
$mailer->setLogger($logger);

$message = new Mail();
$message->setSubject('Test subject');
$message->setTextBody('This is a example message');
$message->addRecipient('to@test.com');
$message->setSender(array('sender@test.com'));
$message->addBcc('bcc@email.com', 'A bcc');
$message->setReplyTo(array('steve@apple.com' => 'Steve'));
$message->setDispositionNotificationTo(array('renatus@brol.net' => 'Renatus','jonny@brol.net' => 'Jonny'));

$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
