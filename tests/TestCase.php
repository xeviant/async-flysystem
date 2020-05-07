<?php

namespace Xeviant\AsyncFlysystem\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Class TestCase
 *
 * @author Carles Escrig i Royo <esroyo@gmail.com>
 */
class TestCase extends PHPUnitTestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        if (!defined('IS_WINDOWS')) {
            define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        }

    }

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

}
