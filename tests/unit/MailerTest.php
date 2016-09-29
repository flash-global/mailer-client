<?php

namespace Tests\Fei\Service\Mailer\Client;

use Codeception\Test\Unit;
use Fei\ApiClient\ApiClientException;
use Fei\ApiClient\Transport\SyncTransportInterface;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Entity\Mail;

class MailerTest extends Unit
{
    public function testLogger()
    {
        $mailer = new Mailer();
        $mailer->setLogger(new Logger());

        $this->assertEquals(new Logger(), $mailer->getLogger());
        $this->assertAttributeEquals($mailer->getLogger(), 'logger', $mailer);
    }

    public function testTransmit()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(true);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('notify');

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitWithLogger()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(true);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('notify');

        $mailer = new Mailer([
            Mailer::OPTION_BASEURL => 'http://url',
            Mailer::OPTION_LOG_MAIL_SENT => true
        ]);
        $mailer->setTransport($transport);
        $mailer->setLogger($logger);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitThrowException()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException(new \Exception('test'));

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('notify');

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);

        $this->expectException(\Exception::class);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitThrowExceptionWithLogger()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException(new \Exception('test'));

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('notify');

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);
        $mailer->setLogger($logger);

        $this->expectException(\Exception::class);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitReturnFalse()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(false);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('notify');

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);

        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitReturnFalseWithLogger()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(false);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('notify');

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);
        $mailer->setLogger($logger);

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

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->transmit(new Mail());
    }

    public function testTransmitNoServer()
    {
        $this->expectException(ApiClientException::class);

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->transmit($this->getValidMailInstance());
    }

    public function testTransmitMailConvertEncoding()
    {
        $transport = $this->createMock(SyncTransportInterface::class);

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url']);
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


    public function testCatchAllAddress()
    {

        $mailer = new Mailer([Mailer::OPTION_BASEURL => 'http://url', Mailer::OPTION_CATCHALL_ADDRESS => 'dev@dev.com']);

        $transport = $this->createMock(SyncTransportInterface::class);
        $mailer->setTransport($transport);

        $mail = $this->createMock(Mail::class);
        $mail->method('getSender')->willReturn(['test@test.com' => 'test@test.com']);
        $mail->method('getTextBody')->willReturn('This a test text body');
        $mail->method('getRecipients')->willReturn(['original@recipient.com' => 'Original recipient']);
        $mail->method('getSubject')->willReturn('This is a test subject');
        $mail->method('getAttachments')->willReturn([]);
        $mail->method('getBcc')->willReturn([]);
        $mail->method('getCc')->willReturn([]);
        $mail->expects($this->exactly(2))->method('setRecipients')->with($this->logicalOr(['original@recipient.com' => 'Original recipient'], ['dev@dev.com']));


        $mailer->transmit($mail);
    }


    public function testAuditLoggerIsUsed()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(true);

        $logger = $this->createMock(Logger::class);
        $auditLogger = $this->createMock(Logger::class);
        $auditLogger->expects($this->once())->method('notify');
        $logger->expects($this->never())->method('notify');

        $mailer = new Mailer([
            Mailer::OPTION_BASEURL => 'http://url',
            Mailer::OPTION_LOG_MAIL_SENT => true
        ]);
        $mailer->setTransport($transport);
        $mailer->setLogger($logger);
        $mailer->setAuditLogger($auditLogger);

        $mailer->transmit($this->getValidMailInstance());
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
