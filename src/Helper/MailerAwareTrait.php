<?php
/**
 * MailerAwareTrait.php
 *
 * @date        13/12/17
 * @file        MailerAwareTrait.php
 */

namespace Fei\Service\Mailer\Client\Helper;


use Fei\Service\Mailer\Client\Mailer;

trait MailerAwareTrait
{
    /** @var Mailer */
    protected $mailer;

    /**
     * @return Mailer
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     * @param Mailer $mailer
     *
     * @return $this
     */
    public function setMailer(Mailer $mailer)
    {
        $this->mailer = $mailer;

        return $this;
    }
}