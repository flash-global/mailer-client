<?php

namespace Fei\Service\Mailer\Client;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Fei\Service\Logger\Entity\Notification;
use Fei\Service\Mailer\Entity\Mail;

/**
 * Class PricerMailer
 *
 * @package Fei\Service\Mailer\Client
 */
class PricerMailer extends Mailer
{
    /**
     * @var array
     */
    protected $emailDelimiters = array(',', ';', '-', ' ', "\t");

    /**
     * PricerMailer constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->addCallbackBeforeValidation(array($this, 'sanitizeAddress'));

        parent::__construct($options);
    }

    /**
     * Sanitize email address
     *
     * @param Mail $mail
     */
    public function sanitizeAddress(Mail $mail)
    {
        $validator = new EmailValidator();
        $sanitizer = function (array $address, array $delimiters) use ($mail, $validator) {
            $sanitized = array();
            foreach ($address as $email => $label) {
                $invalid = array();
                $filtered = array_filter(
                    explode(
                        $delimiters[0],
                        str_replace($delimiters, $delimiters[0], str_replace('"', '', $email))
                    ),
                    function ($value) use ($mail, $validator, &$invalid) {
                        if (empty(trim($value))) {
                            return false;
                        }

                        if ($validator->isValid($value, new RFCValidation()) === false) {
                            $invalid[] = sprintf('`%s`', $value);
                            return false;
                        }

                        return true;
                    }
                );

                if (!empty($invalid)) {
                    $message = count($invalid) > 1
                        ? sprintf('"%s are not valid email address', implode(', ', $invalid))
                        : sprintf('"%s is not a valid email address', implode(', ', $invalid));

                    $this->sendAuditNotification((new Notification())
                        ->setNamespace('/mailer/client')
                        ->setCategory(Notification::AUDIT)
                        ->setContext($mail->getContext())
                        ->setMessage($message)
                        ->setLevel(Notification::LVL_WARNING)
                    );
                }

                $isLabel = !($email == $label);
                foreach ($filtered as $value) {
                    $sanitized[$value] = $isLabel ? $label : $value;
                }
            }

            if (!empty($address) && empty($sanitized)) {
                $this->sendAuditNotification(
                    (new Notification())
                        ->setNamespace('/mailer/client')
                        ->setCategory(Notification::AUDIT)
                        ->setContext($mail->getContext())
                        ->setMessage('Got empty address email after address email cleaning')
                        ->setLevel(Notification::LVL_WARNING)
                );
            }

            return $sanitized;
        };

        $mail->setRecipients($sanitizer($mail->getRecipients(), $this->emailDelimiters));
        $mail->setBcc($sanitizer($mail->getBcc(), $this->emailDelimiters));
        $mail->setCc($sanitizer($mail->getCc(), $this->emailDelimiters));
    }

    /**
     * Get EmailDelimiters
     *
     * @return array
     */
    public function getEmailDelimiters()
    {
        return $this->emailDelimiters;
    }

    /**
     * Set EmailDelimiters
     *
     * @param array $emailDelimiters
     *
     * @return $this
     */
    public function setEmailDelimiters(array $emailDelimiters)
    {
        $this->emailDelimiters = $emailDelimiters;

        return $this;
    }
}
