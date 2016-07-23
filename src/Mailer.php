<?php

namespace Fei\Service\Mailer\Client;

use Fei\ApiClient\AbstractApiClient;
use Fei\ApiClient\ApiRequestOption;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\Transport\BasicTransport;
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
    /**
     * @var array Supported encodings. Order matter.
     * @see http://php.net/manual/en/mbstring.supported-encodings.php
     */
    protected $encodings = array(
        'ASCII', 'UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1', 'UCS-4', 'UCS-4BE', 'UCS-4LE', 'UCS-2',
        'UCS-2BE', 'UCS-2LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-7', 'UTF7-IMAP',
        'EUC-JP', 'SJIS', 'eucJP-win', 'SJIS-win', 'ISO-2022-JP', 'ISO-2022-JP-MS', 'CP932', 'CP51932', 'SJIS-mac',
        'JIS', 'JIS-ms', 'CP50220', 'CP50220raw', 'CP50221', 'CP50222', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4',
        'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
        'ISO-8859-14', 'byte2be', 'byte2le', 'byte4be', 'byte4le', 'BASE64', '7bit', '8bit', 'EUC-CN',  'CP936', 'HZ',
        'EUC-TW', 'CP950', 'BIG-5', 'EUC-KR', 'UHC', 'ISO-2022-KR', 'Windows-1251', 'CP866', 'KOI8-R', 'ArmSCII-8',
        'CP850'
    );

    /**
     * Mailer constructor.
     *
     * @param string $baseUrl The Mailer API base URL
     */
    public function __construct($baseUrl)
    {
        $this->setBaseUrl($baseUrl);
    }

    /**
     * Transmit mails
     *
     * @param Mail  $mail
     * @param array $context
     *
     * @return bool|\Fei\ApiClient\ResponseDescriptor
     */
    public function transmit(Mail $mail, array $context = array())
    {
        $validator = new MailValidator();
        if (!$validator->validate($mail)) {
            throw new \LogicException(sprintf("Mail instance is not valid:\n%s", $validator->getErrorsAsString()));
        }

        $mail = $this->toUtf8($mail);

        $request = new RequestDescriptor();

        Json::$useBuiltinEncoderDecoder = true;

        try {
            $request->addBodyParam('mail', Json::encode($mail));
        } catch (\Exception $e) {
            throw new \LogicException(
                sprintf('Unable to serialize mail: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        if (!empty($context)) {
            try {
                $request->addBodyParam('context', Json::encode($context));
            } catch (\Exception $e) {
                throw new \LogicException(
                    sprintf('Unable to serialize context: %s', $e->getMessage()),
                    $e->getCode(),
                    $e
                );
            }

        }

        $request->setUrl($this->buildUrl('/api/mails'));
        $request->setMethod('POST');

        if (null === $this->getTransport()) {
            $this->setTransport(new BasicTransport());
        }

        return $this->send($request, ApiRequestOption::NO_RESPONSE);
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
