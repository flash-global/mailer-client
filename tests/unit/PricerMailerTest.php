<?php

namespace Tests\Fei\Service\Mailer\Client;

use Codeception\Test\Unit;
use Fei\ApiClient\Transport\SyncTransportInterface;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Mailer\Client\Mailer;
use Fei\Service\Mailer\Client\PricerMailer;
use Fei\Service\Mailer\Entity\Mail;

/**
 * Class PricerMailerTest
 *
 * @package Tests\Fei\Service\Mailer\Client
 */
class PricerMailerTest extends Unit
{
    public function testEmailDelimiterAccessors()
    {
        $mailer = new PricerMailer();
        $mailer->setEmailDelimiters(['test']);

        $this->assertEquals(['test'], $mailer->getEmailDelimiters());
        $this->assertAttributeEquals($mailer->getEmailDelimiters(), 'emailDelimiters', $mailer);
    }

    public function testAddressFilter()
    {
        $mail = new Mail();
        $mail->setRecipients([
            'abc/cde@mail.com',
            'dest1@domain.com, dest2@domain.com ; dest3@domain.com dest4@domain.com',
            'peterb@cargospectrum.com	sergeys@cargospectrum.com	martinh@cargospectrum.com'
        ]);

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([
            'abc/cde@mail.com'=>'abc/cde@mail.com',
            'dest1@domain.com' => 'dest1@domain.com',
            'dest2@domain.com' => 'dest2@domain.com',
            'dest3@domain.com' => 'dest3@domain.com',
            'dest4@domain.com' => 'dest4@domain.com',
            'peterb@cargospectrum.com' => 'peterb@cargospectrum.com',
            'sergeys@cargospectrum.com' => 'sergeys@cargospectrum.com',
            'martinh@cargospectrum.com' =>  'martinh@cargospectrum.com'
        ],
            $mail->getRecipients()
        );
    }

    public function testAddressFilterEmailNull()
    {
        $mail = new Mail();
        $mail->addRecipient([null]);

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([], $mail->getRecipients());
    }
    public function testAddressFilterEmailNullWithLogger()
    {
        $mail = new Mail();
        $mail->setRecipients([null]);

        $notifications = [];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->any())->method('notify')->willReturnCallback(
            function ($notification) use (&$notifications) {
                $notifications[] = $notification;
            }
        );

        $mailer = (new PricerMailer())->setAuditLogger($logger);

        $mailer->sanitizeAddress($mail);

        $this->assertCount(0, $notifications);
    }

    public function testAddressFilterEmailEmpty()
    {
        $mail = new Mail();
        $mail->setRecipients(['']);

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([], $mail->getRecipients());
    }
    public function testAddressFilterAll()
    {
        $mail = new Mail();
        $mail->setRecipients(['not even a address email, for ever !']);

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([], $mail->getRecipients());
    }

    public function testAddressFilterAllWithLogger()
    {
        $mail = new Mail();
        $mail->setRecipients(['not even a address email, for ever !']);

        $notifications = [];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->any())->method('notify')->willReturnCallback(
            function ($notification) use (&$notifications) {
                $notifications[] = $notification;
            }
        );

        $mailer = (new PricerMailer())->setAuditLogger($logger);

        $mailer->sanitizeAddress($mail);

        $this->assertEquals(
            [
                '"`not`, `even`, `a`, `address`, `email`, `for`, `ever`, `!` are not valid email address',
                'Got empty address email after address email cleaning'
            ],
            array_map(function ($notification) { return $notification->getMessage(); }, $notifications)
        );
    }

    public function testAddressFilterAllWithLoggerSingular()
    {
        $mail = new Mail();
        $mail->setRecipients(['notaemail', 'notevenamail']);

        $notifications = [];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->any())->method('notify')->willReturnCallback(
            function ($notification) use (&$notifications) {
                $notifications[] = $notification;
            }
        );

        $mailer = (new PricerMailer())->setAuditLogger($logger);

        $mailer->sanitizeAddress($mail);

        $this->assertEquals(
            [
                '"`notaemail` is not a valid email address',
                '"`notevenamail` is not a valid email address',
                'Got empty address email after address email cleaning'
            ],
            array_map(function ($notification) { return $notification->getMessage(); }, $notifications)
        );
    }

    public function testAddressFilterWithLabel()
    {
        $mail = new Mail();
        $mail->setRecipients(['dest1@domain.com, dest2@domain.com ; dest3@domain.com dest4@domain.com ; abc/cde@mail.com' => 'a label']);

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([
            'abc/cde@mail.com' => 'a label',
            'dest1@domain.com' => 'a label',
            'dest2@domain.com' => 'a label',
            'dest3@domain.com' => 'a label',
            'dest4@domain.com' => 'a label'
        ],
            $mail->getRecipients()
        );
    }

    public function testAddressFilterWithBadEmailAddress()
    {
        $mail = new Mail();
        $mail->setRecipients(
            ['abc/cde@mail.com, dest1@domain.com, notaaddressemail ; dest3@domain.com dest4@domain.com another not a email']
        );

        $mailer = new PricerMailer();

        $mailer->sanitizeAddress($mail);

        $this->assertEquals([
            'abc/cde@mail.com'=> 'abc/cde@mail.com',
            'dest1@domain.com' => 'dest1@domain.com',
            'dest3@domain.com' => 'dest3@domain.com',
            'dest4@domain.com' => 'dest4@domain.com'
        ],
            $mail->getRecipients()
        );
    }

    public function testAddressFilterWithBadEmailAddressAndLogging()
    {
        $mail = new Mail();
        $mail->setRecipients(
            ['abc/cde@mail.com , dest1@domain.com, notaaddressemail ; dest3@domain.com dest4@domain.com another not a email']
        );

        $notifications = [];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->any())->method('notify')->willReturnCallback(
            function ($notification) use (&$notifications) {
                $notifications[] = $notification;
            }
        );

        $mailer = (new PricerMailer())->setAuditLogger($logger);

        $mailer->sanitizeAddress($mail);

        $this->assertEquals(
            ['"`notaaddressemail`, `another`, `not`, `a`, `email` are not valid email address'],
            array_map(function ($notification) { return $notification->getMessage(); }, $notifications)
        );
    }

    public function testAddressFilterAndTransmit()
    {
        $mail = $this->getValidMailInstance();
        $mail->setRecipients(
            ['abc/cde@mail.com, dest1@domain.com, notaaddressemail ; dest3@domain.com dest4@domain.com another not a email']
        );

        $mail->addBcc('abc/cde@mail.com|dest1@domain.com|notaaddressemail|dest3@domain.com|dest4@domain.com', 'Test label');
        $mail->addCc('abc/cde@mail.com|dest1@domain.com,dest3@domain.com,; dest4@domain.com', '');

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(true);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('notify');

        $mailer = new PricerMailer([Mailer::OPTION_BASEURL => 'http://url']);
        $mailer->setTransport($transport);

        $mailer->transmit($mail);

        $this->assertEquals(
            [
                'abc/cde@mail.com' => 'abc/cde@mail.com',
                'dest1@domain.com' => 'dest1@domain.com',
                'dest3@domain.com' => 'dest3@domain.com',
                'dest4@domain.com' => 'dest4@domain.com'
            ], $mail->getRecipients()
        );
        $this->assertEquals([], $mail->getBcc());
        $this->assertEquals(
            [
                'abc/cde@mail.com' => 'abc/cde@mail.com',
                'dest1@domain.com' => 'dest1@domain.com',
                'dest3@domain.com' => 'dest3@domain.com',
                'dest4@domain.com' => 'dest4@domain.com'
            ], $mail->getRecipients()
        );
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
