<?php

require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Attachment;
use Fei\Service\Mailer\Entity\Mail;

$mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://127.0.0.1:8081']);

$message = new Mail();
$message->setSubject('Test subject');
$message->addRecipient('test@example.com');
$message->setSender(array('sender@test.com'));
$message->addBcc('gentleman@example.com', 'Another Gentleman');
$message->setReplyTo(array('steve@apple.com' => 'Steve'));

$embedded = (new Attachment(__DIR__ . '/../tests/_data/avatar.png', true));
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
        <img src="{$embedded->getCid()}" style="display: block; width: 100px; height: 100px; float: right;"">
        This is example with a embedded image
    </p>
</body>
</html>
HTML
);

$return = $mailer->transmit($message);

if ($return) {
    echo 'Mail transmit success' . PHP_EOL;
} else {
    echo 'Mail transmit failed' . PHP_EOL;
}
