<?php

namespace Fei\Service\Mailer\Client;

use Fei\ApiClient\AbstractApiClient;
use Fei\ApiClient\ApiRequestOption;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Logger\Entity\Notification;
use Fei\Service\Mailer\Entity\Mail;
use Fei\Service\Mailer\Validator\MailValidator;
use Zend\Json\Json;

/**
 * Class Mailer
 *
 * @package Fei\Service\Mailer\Client
 */
class Mailer extends AbstractApiClient
{

    const OPTION_CATCHALL_ADDRESS = 'catchallAddress';
    const OPTION_LOG_MAIL_SENT = 'logAllMailInfo';

    /**
     * @var string
     */
    protected $catchallAddress;

    /**
     * @var Logger
     */
    protected $logger;

    /** @var  Logger */
    protected $auditLogger;

    /**
     * @var array Supported encodings. Order matter.
     * @see http://php.net/manual/en/mbstring.supported-encodings.php
     */
    protected $encodings = [
        'ASCII', 'UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1', 'UCS-4', 'UCS-4BE', 'UCS-4LE', 'UCS-2',
        'UCS-2BE', 'UCS-2LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-7', 'UTF7-IMAP',
        'EUC-JP', 'SJIS', 'eucJP-win', 'SJIS-win', 'ISO-2022-JP', 'ISO-2022-JP-MS', 'CP932', 'CP51932', 'SJIS-mac',
        'JIS', 'JIS-ms', 'CP50220', 'CP50220raw', 'CP50221', 'CP50222', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4',
        'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
        'ISO-8859-14', 'byte2be', 'byte2le', 'byte4be', 'byte4le', 'BASE64', '7bit', '8bit', 'EUC-CN',  'CP936', 'HZ',
        'EUC-TW', 'CP950', 'BIG-5', 'EUC-KR', 'UHC', 'ISO-2022-KR', 'Windows-1251', 'CP866', 'KOI8-R', 'ArmSCII-8',
        'CP850'
    ];

    /**
     * @var callable[]
     */
    protected $callbackBeforeValidation = [];

    /**
     * Transmit mails
     *
     * @param Mail  $mail
     * @param array $context
     *
     * @return bool|\Fei\ApiClient\ResponseDescriptor
     *
     * @throws \Exception
     */
    public function transmit(Mail $mail, array $context = array())
    {
        $this->executeCallbackBeforeValidation($mail);

        $notification = new Notification();
        $notification
            ->setNamespace('/mailer/client')
            ->setCategory(Notification::AUDIT)
            ->setContext($mail->getContext());

        $validator = new MailValidator();
        if (!$validator->validate($mail)) {
            if(empty($this->getLogger())) {
                throw new \LogicException(sprintf("Mail instance is not valid:\n%s", $validator->getErrorsAsString()));
            }

            $notification
                ->setMessage(sprintf(sprintf("Mail instance is not valid:\n%s", $validator->getErrorsAsString())))
                ->setLevel(Notification::LVL_ERROR);

            $this->getLogger()->notify($notification);

            return false;
        }

        if($this->getOption(self::OPTION_LOG_MAIL_SENT)) {
            if(empty($this->getAuditLogger())) {
                if(empty($this->getLogger())) {
                    throw new \LogicException("A logger has to be set for logging mails.");
                }
                $this->setAuditLogger($this->getLogger());
            }
        }

        // handle recipient rerouting if needed
        if($catchall = $this->getOption(self::OPTION_CATCHALL_ADDRESS))
        {
            $recipients = $mail->getRecipients();
            $cc = $mail->getCc();
            $bcc = $mail->getBcc();

            $info = '**************************************************' . PHP_EOL;
            $info .= 'Original recipients: ' . "\t" . implode(', ', $recipients) . PHP_EOL;
            if($cc) $info .= 'Original cc: ' . "\t" . implode(', ', $cc) . PHP_EOL;
            if($bcc) $info .= 'Original bcc: ' . "\t" . implode(', ', $bcc) . PHP_EOL;
            $info .= '**************************************************' . PHP_EOL;

            if($mail->getTextBody()) $mail->setTextBody($info . $mail->getTextBody());
            if($mail->getHtmlBody()) $mail->setHtmlBody($info . $mail->getHtmlBody());

            $mail->setSubject('[Caught] ' . $mail->getSubject());
            $mail->clearRecipients();
            $mail->clearCc();
            $mail->clearBcc();

            $mail->setRecipients([$catchall]);
        }

        $mail = $this->toUtf8($mail);

        $request = new RequestDescriptor();

        Json::$useBuiltinEncoderDecoder = true;

        try {
            $encoded = Json::encode($mail);
        } catch (\Exception $e) {
            throw new \LogicException(
                sprintf('Unable to serialize mail: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        $request->addBodyParam('mail', $encoded);

        if (!empty($context)) {
            try {
                $encoded = Json::encode($context);
            } catch (\Exception $e) {
                throw new \LogicException(
                    sprintf('Unable to serialize context: %s', $e->getMessage()),
                    $e->getCode(),
                    $e
                );
            }

            $request->addBodyParam('context', $encoded);
        }

        $request->setUrl($this->buildUrl('/api/mails'));
        $request->setMethod('POST');

        if (null === $this->getTransport()) {
            $this->setTransport(new BasicTransport());
        }

        $notification = new Notification();
        $notification
            ->setNamespace('/mailer/client')
            ->setCategory(Notification::AUDIT)
            ->setContext($mail->getContext());

        try {
            $response = $this->send($request, ApiRequestOption::NO_RESPONSE);

            if ($response && $this->getAuditLogger() instanceof Logger) {
                $notification
                    ->setMessage('Successfully sent mail')
                    ->setLevel(Notification::LVL_INFO)
                ;

                $this->getAuditLogger()->notify($notification);
            }
        } catch (\Exception $e) {
            if ($this->getLogger() instanceof Logger) {
                $notification
                    ->setMessage(sprintf('Failed to sent mail (%s)', $e->getMessage()))
                    ->setLevel(Notification::LVL_ERROR);

                $this->getLogger()->notify($notification);
            }

            throw $e;
        }

        if (!$response && $this->getLogger() instanceof Logger) {
            $notification
                ->setMessage('Failed to sent mail')
                ->setLevel(Notification::LVL_ERROR);

            $this->getLogger()->notify($notification);
        }

        return $response;
    }

    /**
     * Get logger client instance
     *
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set logger client instance
     *
     * @param Logger $logger
     *
     * @return $this
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return Logger
     */
    public function getAuditLogger()
    {
        return $this->auditLogger;
    }

    /**
     * @param Logger $auditLogger
     *
     * @return $this
     */
    public function setAuditLogger($auditLogger)
    {
        $this->auditLogger = $auditLogger;

        return $this;
    }

    /**
     * Get CallbackBeforeValidation
     *
     * @return \callable[]
     */
    public function getCallbackBeforeValidation()
    {
        return $this->callbackBeforeValidation;
    }

    /**
     * Add a callback to be executed before validation.
     * The function or method accepts a Mail instance as parameter
     *
     * function (Mail $mail) {
     *     // Do some stuff with Mail instance
     * }
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addCallbackBeforeValidation(callable $callable)
    {
        $this->callbackBeforeValidation[] = $callable;

        return $this;
    }

    /**
     * Add a callback to be executed before validation in first position
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFirstCallbackBeforeValidation(callable $callable)
    {
        array_unshift($this->callbackBeforeValidation, $callable);

        return $this;
    }

    /**
     * Clear the registered callback stack
     *
     * @return $this
     */
    public function clearCallbackBeforeValidation()
    {
        $this->callbackBeforeValidation = [];

        return $this;
    }

    /**
     * Execute registered callbacks
     *
     * @param Mail $mail
     */
    protected function executeCallbackBeforeValidation(Mail $mail)
    {
        foreach ($this->getCallbackBeforeValidation() as $callback) {
            $callback($mail);
        }
    }

    /**
     * Send a notification to Audit Logger
     *
     * @param Notification $notification
     */
    protected function sendAuditNotification(Notification $notification)
    {
        if ($this->getAuditLogger() instanceof Logger) {
            $this->getAuditLogger()->notify($notification);
        }
    }

    /**
     * Convert Mail properties content to UTF-8
     *
     * @param Mail $mail
     *
     * @return Mail
     */
    protected function toUtf8(Mail $mail)
    {
        if (!$this->isUtf8($mail->getSubject())) {
            $mail->setSubject($this->convertStrToUtf8($mail->getSubject()));
        }

        if (!$this->isUtf8($mail->getTextBody())) {
            $mail->setTextBody($this->convertStrToUtf8($mail->getTextBody()));
        }

        if (!$this->isUtf8($mail->getHtmlBody())) {
            $mail->setHtmlBody($this->convertStrToUtf8($mail->getHtmlBody()));
        }

        $mail->setSender($this->convertArrayToUtf8($mail->getSender()));
        $mail->setRecipients($this->convertArrayToUtf8($mail->getRecipients()));
        $mail->setCc($this->convertArrayToUtf8($mail->getCc()));
        $mail->setBcc($this->convertArrayToUtf8($mail->getBcc()));

        return $mail;
    }

    /**
     * Tells if string is UTF-8 encoding
     *
     * @param $str
     *
     * @return bool
     */
    protected function isUtf8($str)
    {
        return mb_check_encoding($str, 'UTF-8');
    }

    /**
     * Convert string to UTF-8
     *
     * @param $str
     *
     * @return mixed|string
     */
    protected function convertStrToUtf8($str)
    {
        $encoding = mb_detect_encoding($str, $this->encodings, true);
        if ($encoding === false) {
            throw new \LogicException(sprintf('Unable to detect encoding of `%s`', $str));
        }

        if ($encoding != 'UTF-8') {
            return mb_convert_encoding($str, 'UTF-8', $encoding);
        }

        return $str;
    }

    /**
     * Convert array content in UTF-8
     *
     * @param array $array
     *
     * @return array
     */
    protected function convertArrayToUtf8(array $array)
    {
        $converted = array();
        foreach ($array as $key => $value) {
            if (!is_int($key) && !$this->isUtf8($key)) {
                $key = $this->convertStrToUtf8($key);
            }

            if (!is_array($value)) {
                if (!$this->isUtf8($value)) {
                    $value = $this->convertStrToUtf8($value);
                }
            }

            $converted[$key] = $value;
        }

        return $converted;
    }
}
