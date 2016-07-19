<?php

use Fei\ApiClient\Transport\TransportInterface;
use Fei\Service\Logger\Entity\Notification;
use Pricer\Logger\Client\Logger;

class LoggerTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /** @var  Logger */
    protected $logger;

    /** @var  Faker\Generator */
    protected $faker;

    protected function _before()
    {
        $this->faker = \Faker\Factory::create('fr_FR');
    }

    public function testLoggerCanCommit()
    {
        $logger = new Logger();

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMany');
        $logger->setTransport($transport);

        $logger->begin();
        $logger->notify('test');
        $logger->commit();
    }

    public function testLoggerCanDelay()
    {
        $logger = new Logger();

        $logger->begin();
        $this->assertAttributeEquals(true, 'isDelayed', $logger);
    }

    public function testLoggerCanNotify()
    {
        $logger = new Logger();
        $logger->setBaseUrl('http://azeaze.fr/');

        $notification = new Notification();
        $notification->setMessage($this->faker->sentence);
        $notification->setLevel(Notification::LVL_INFO);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send');
        $logger->setTransport($transport);

        $logger->notify($notification);
    }



}