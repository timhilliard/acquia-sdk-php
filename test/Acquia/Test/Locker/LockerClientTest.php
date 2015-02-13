<?php

namespace Acquia\Test\Locker;

use Acquia\Locker\LockerClient;
use Acquia\Locker\LockerAuthPlugin;

class LockerClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Acquia\Test\Locker\LockerRequestListener
     */
    protected $requestListener;

    /**
     * @param string|null $responseFile
     * @param int $responseCode
     *
     * @return \Acquia\Locker\LockerClient
     */
    public function getLockerClient($responseFile = null, $responseCode = 200)
    {
        $locker = LockerClient::factory(array(
            'base_url' => 'https://locker.example.com',
            'username' => 'test-username',
            'password' => 'test-password',
        ));

        $this->requestListener = new LockerRequestListener();
        $locker->getEventDispatcher()->addSubscriber($this->requestListener);

        if ($responseFile !== null) {
            $this->addMockResponse($locker, $responseFile, $responseCode);
        }

        return $locker;
    }

    /**
     * @param \Acquia\Locker\LockerApiClient $locker
     * @param string $responseFile
     */
    public function addMockResponse(LockerClient $locker, $responseFile, $responseCode)
    {
        $mock = new \Guzzle\Plugin\Mock\MockPlugin();

        $response = new \Guzzle\Http\Message\Response($responseCode);
        if (is_string($responseFile)) {
            $response->setBody(file_get_contents($responseFile));
        }

        $mock->addResponse($response);
        $locker->addSubscriber($mock);
    }

    /**
     * Helper function that returns the LockerAuthPlugin listener.
     *
     * @param \Acquia\Locker\LockerApiClient $locker
     *
     * @return \Acquia\Locker\LockerAuthPlugin
     *
     * @throws \UnexpectedValueException
     */
    public function getRegisteredAuthPlugin(LockerClient $locker)
    {
        $listeners = $locker->getEventDispatcher()->getListeners('request.before_send');
        foreach ($listeners as $listener) {
            if (isset($listener[0]) && $listener[0] instanceof LockerAuthPlugin) {
                return $listener[0];
            }
        }

        throw new \UnexpectedValueException('Expecting subscriber Acquia\Locker\LockerAuthPlugin to be registered');
    }

    public function testGetBuilderParams()
    {
        $expected = array (
            'base_url' => 'https://locker.example.com',
            'username' => 'test-username',
            'password' => 'test-password',
        );

        $locker = $this->getLockerClient();
        $this->assertEquals($expected, $locker->getBuilderParams());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequireUsername()
    {
        LockerClient::factory(array(
            'password' => 'test-password',
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequirePassword()
    {
        LockerClient::factory(array(
            'username' => 'test-username',
        ));
    }

    public function testGetBasePath()
    {
        $locker = $this->getLockerClient();
        $this->assertEquals('/locks', $locker->getConfig('base_path'));
    }

    public function testHasAuthPlugin()
    {
        $locker = $this->getLockerClient();
        $hasPlugin = (boolean) $this->getRegisteredAuthPlugin($locker);
        return $this->assertTrue($hasPlugin);
    }

    public function testGetResponseBody()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/getLock.json');
        $response = $locker->getLock('getLock');
        $this->assertEquals('getLock', (string) $response);
    }

    public function testCallGetLock()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/getLock.json');
        $response = $locker->getLock('getLock');

        $this->assertEquals('https://locker.example.com/locks/getLock.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Locker\Response\Lock', $response);
        $this->assertEquals('getLock', (string) $response);

        $this->assertEquals('ok', $response->status());
        $this->assertEquals('getLock', $response->lock_id());
        $this->assertEquals('getLock-89ab-cdef-0123-456789abcdef', $response->data('uuid'));
        $this->assertEquals(30, $response->data('ttl'));
        $this->assertEquals('getLock test message', $response->data('message'));
        $this->assertEquals(15, $response->data('timeout'));
    }

    public function testCallGetLockNotFound()
    {
        $this->setExpectedException(
            'Acquia\Locker\Exception\LockNotFound',
            "Lock 'getLockNotFound' not found."
        );

        $locker = $this->getLockerClient(__DIR__ . '/json/getLockNotFound.json', 404);
        $response = $locker->getLock('getLockNotFound');
    }

    public function testCallAcquireLock()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/acquireLock.json');
        $response = $locker->acquireLock('acquireLock', 123, 'My acquireLock message.');

        $this->assertEquals('https://locker.example.com/locks/acquireLock.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Locker\Response\Lock', $response);
        $this->assertEquals('acquireLock', (string) $response);

        $this->assertEquals('ok', $response->status());
        $this->assertEquals('acquireLock', $response->lock_id());
        $this->assertEquals('acquireLock-89ab-cdef-0123-456789abcdef', $response->data('uuid'));
    }

    /**
     * @todo test the retry functionality. There seems to be an issue with using
     *         backoff functionality and trying to test it with a Mock Plugin.
     */
    public function testCallAcquireLockTimeout()
    {
        $this->setExpectedException(
            'Acquia\Locker\Exception\LockAcquireTimeout',
            "Could not acquire lock 'acquireLockTaken' in 0 seconds."
        );
        $locker = $this->getLockerClient(__DIR__ . '/json/acquireLockTaken.json', 409);
        $response = $locker->acquireLock('acquireLockTaken', 30, 'My acquireLockTaken message.', 0);
    }

    public function testCallRenewLock()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/renewLock.json');
        $response = $locker->renewLock('renewLock', 'renewLock-89ab-cdef-0123-456789abcdef', 123);

        $this->assertEquals('https://locker.example.com/locks/renewLock.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Locker\Response\Lock', $response);
        $this->assertEquals('renewLock', (string) $response);

        $this->assertEquals('ok', $response->status());
        $this->assertEquals('renewLock', $response->lock_id());
    }

    public function testCallRenewLockUuidMismatch()
    {
        $this->setExpectedException(
            'Acquia\Locker\Exception\LockUuidMismatch',
            'Lock UUID mismatch.'
        );
        $locker = $this->getLockerClient(__DIR__ . '/json/renewLockUuidMismatch.json', 409);
        $response = $locker->renewLock('renewLockUuidMismatch', 'renewLockUuidMismatch-89ab-cdef-0123-456789abcdef', 123);
    }

    public function testCallReleaseLock()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/releaseLock.json');
        $response = $locker->releaseLock('releaseLock', 'releaseLock-89ab-cdef-0123-456789abcdef', 123);

        $this->assertEquals('https://locker.example.com/locks/releaseLock.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Locker\Response\Lock', $response);
        $this->assertEquals('releaseLock', (string) $response);

        $this->assertEquals('ok', $response->status());
        $this->assertEquals('releaseLock', $response->lock_id());
    }

    public function testCallReleaseLockForce()
    {
        $locker = $this->getLockerClient(__DIR__ . '/json/releaseLockForce.json');
        $response = $locker->releaseLock('releaseLockForce', 'releaseLockForce-89ab-cdef-0123-456789abcdef', 123);

        $this->assertEquals('https://locker.example.com/locks/releaseLockForce.json', $this->requestListener->getUrl());
        $this->assertInstanceOf('\Acquia\Locker\Response\Lock', $response);
        $this->assertEquals('releaseLockForce', (string) $response);

        $this->assertEquals('ok', $response->status());
        $this->assertEquals('releaseLockForce', $response->lock_id());
    }

    public function testCallReleaseLockUuidMismatch()
    {
        $this->setExpectedException(
            'Acquia\Locker\Exception\LockUuidMismatch',
            'Lock UUID mismatch or Lock not found.'
        );
        $locker = $this->getLockerClient(__DIR__ . '/json/releaseLockUuidMismatch.json', 409);
        $response = $locker->releaseLock('releaseLockUuidMismatch', 'releaseLockUuidMismatch-89ab-cdef-0123-456789abcdef');
    }
}
