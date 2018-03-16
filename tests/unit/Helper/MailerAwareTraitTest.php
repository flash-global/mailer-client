<?php
/**
 * MailerAwareTraitTest.php
 *
 * @date        13/12/17
 * @file        MailerAwareTraitTest.php
 */

namespace Tests\Fei\Service\Mailer\Client\Helper;

use Fei\Service\Mailer\Client\Helper\MailerAwareInterface;
use Fei\Service\Mailer\Client\Helper\MailerAwareTrait;
use Fei\Service\Mailer\Client\Mailer;
use Codeception\Test\Unit;

/**
 * MailerAwareTraitTest
 */
class MailerAwareTraitTest extends Unit
{
    public function testSetGetMailer()
    {
        $stub = new StubMailerTrait();

        $mailer = new Mailer();

        $stub->setMailer($mailer);

        $this->assertInstanceOf(Mailer::class, $stub->getMailer());
    }
}

class StubMailerTrait implements MailerAwareInterface
{
    use MailerAwareTrait;
}