<?php

namespace Tests\Fei\Service\Mailer\Client;

use Codeception\Test\Unit;
use Fei\ApiClient\ApiClientException;
use Fei\ApiClient\Transport\TransportInterface;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;

class LoggerTest extends Unit
{
    public function testTransmit()
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send');

        $mailer = new Mailer('http://url');
        $mailer->setTransport($transport);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitMailNoValid()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(<<<HEREDOC
Mail instance is not valid:
subject: Subject is empty; body: Both text and html bodies are empty; sender: Sender is null; recipients: Recipients is empty
HEREDOC
        );

        $mailer = new Mailer('http://url');
        $mailer->transmit(new Mail());
    }

    public function testTransmitNoServer()
    {
        $this->expectException(ApiClientException::class);

        $mailer = new Mailer('http://url');
        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitMailConvertEncoding()
    {
        $transport = $this->createMock(TransportInterface::class);

        $mailer = new Mailer('http://url');
        $mailer->setTransport($transport);

        $mail = $this->getValidMailInstance();

        $cp1252 = trim(file_get_contents(__DIR__ . '/../_data/CP1252.txt'));
        $iso885915 = trim(file_get_contents(__DIR__ . '/../_data/ISO-8859-15.txt'));
        $iso88591 = trim(file_get_contents(__DIR__ . '/../_data/ISO-8859-1.txt'));

        $this->assertTrue(mb_check_encoding($cp1252, 'Windows-1252'));
        $this->assertTrue(mb_check_encoding($iso885915, 'ISO-8859-15'));
        $this->assertTrue(mb_check_encoding($iso88591, 'ISO-8859-1')); // Will be converted to UTF-8 like ISO-8859-15 :-/

        foreach ([$cp1252 => '‚ƒŒšœ•', $iso885915 => 'éèà€', $iso88591 => 'éà?'] as $encoded => $value) {
            $mail->setSubject($encoded);
            $mail->setTextBody($encoded);
            $mail->setHtmlBody($encoded);

            $mail->setSender(['test@test.com' => $encoded]);

            $mailer->transmit($mail);

            $this->assertTrue(mb_check_encoding($mail->getSubject(), 'UTF-8'));
            $this->assertTrue(mb_check_encoding($mail->getTextBody(), 'UTF-8'));
            $this->assertTrue(mb_check_encoding($mail->getHtmlBody(), 'UTF-8'));
            $this->assertTrue(mb_check_encoding($mail->getSender()['test@test.com'], 'UTF-8'));

            $this->assertEquals($mail->getSubject(), $value);
        }
    }

    /**
     * @return Mail
     */
    private function getValidMailInstance()
    {
        $mail = new Mail();

        return $mail->setSubject('Test')
            ->setTextBody('Test')
            ->setSender(['tes@email.com'])
            ->setRecipients(['test@test.com']);
    }
}
