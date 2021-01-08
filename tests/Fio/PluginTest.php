<?php

/**
 * @see       https://github.com/lukestokes/fio-client for the canonical source repository
 * @copyright https://github.com/lukestokes/fio-php-sdk/blob/main/copyrght-or-license-file
 * @license   https://github.com/lukestokes/fio-php-sdk/blob/main/need-to-pick-a-license
 */

namespace xtypeTest\Fio;

use xtype\Fio\Client as FioClient;
use xtype\Fio\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    private const BASE_URI = 'http://testnet.fioprotocol.io/v1/chain/';

    /** @var Plugin */
    private $plugin;

    public function setUp(): void
    {
        $this->plugin = new Plugin(
            '',
            new FioClient()
        );
    }

    public function tearDown(): void
    {
        unset($this->plugin);
    }

    /**
     * With magic __call() method, not much to test yet. The action is all
     * in xtype\Fio\Client class
     */
    public function testSomething()
    {
        $this->assertInstanceOf('xtype\Fio\Plugin', $this->plugin);
    }
}