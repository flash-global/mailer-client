<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Faker\Factory;
use Pricer\Logger\Client\Logger;
use ReflectionClass;

class Unit extends \Codeception\Module
{


    public function getRandomLevel()
    {
        $faker = Factory::create('fr_FR');

        return $faker->randomElement(array(
            Logger::LEVEL_DEBUG,
            Logger::LEVEL_ERROR,
            Logger::LEVEL_INFO,
            Logger::LEVEL_PANIC,
            Logger::LEVEL_WARNING,
        ));
    }

    public function getRandomCategory()
    {
        $faker = Factory::create('fr_FR');

        return $faker->randomElement(array(
            Logger::CATEGORY_BUSINESS,
            Logger::CATEGORY_PERFORMANCE,
            Logger::CATEGORY_SECURITY,
        ));
    }

    public function getMethod($name, $class) {
        $class = new ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;

    }

}
