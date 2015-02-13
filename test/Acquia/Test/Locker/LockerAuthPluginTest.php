<?php

namespace Acquia\Test\Locker;

use Acquia\Locker\LockerAuthPlugin;

class LockerAuthPluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \Acquia\Locker\LockerAuthPlugin
     */
    public function getAuthPlugin()
    {
        return new LockerAuthPlugin('test-username', 'test-password');
    }

    public function testGetters()
    {
        $plugin = $this->getAuthPlugin();
        $this->assertEquals('test-username', $plugin->getUsername());
        $this->assertEquals('test-password', $plugin->getPassword());
    }
}
