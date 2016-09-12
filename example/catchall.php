<?php

require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;

$mailer = new Mailer(array(Mailer::OPTION_BASEURL => 'http://127.0.0.1:8081'));

$message = new Mail();
$message->setSubject('This mail has been caught.');
$message->setTextBody('This is a example message');
$message->addRecipient('g.delamarre@flash-global.net');
$message->setSender(array('sender@test.com'));

$mailer->setOption(Mailer::OPTION_CATCHALL_ADDRESS, 'gde@opcoding.eu');

$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
