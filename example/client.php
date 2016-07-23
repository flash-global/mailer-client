<?php

require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;

$mailer = new Mailer('http://127.0.0.1:8081');

$message = new Mail();
$message->setSubject('И');
$message->setTextBody('This is a example message');
$message->addRecipient('to@test.com');
$message->setSender(array('sender@test.com'));

$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
