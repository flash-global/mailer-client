<?php
/**
 * MailerAwareInterface.php
 *
 * @date        13/12/17
 * @file        MailerAwareInterface.php
 */

namespace Fei\Service\Mailer\Client\Helper;

use Fei\Service\Mailer\Client\Mailer;

interface MailerAwareInterface
{
    public function getMailer();

    public function setMailer(Mailer $mailer);
}