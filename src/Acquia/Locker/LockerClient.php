<?php

namespace Acquia\Locker;

use Acquia\Rest\ServiceManagerAware;
use Guzzle\Common\Collection;
use Acquia\Json\Json;
use Guzzle\Service\Client;

class LockerClient extends Client implements ServiceManagerAware
{
    const BASE_URL         = 'http://localhost:8010';
    const BASE_PATH        = '/locks';
    const API_VERSION      = 'v1';

    /**
     * {@inheritdoc}
     *
     * @return \Acquia\Locker\LockerClient
     */
    public static function factory($config = array())
    {
        $required = array(
            'base_url',
            'username',
            'password',
        );

        $defaults = array(
            'base_url' => self::BASE_URL,
            'base_path' => self::BASE_PATH,
        );

        $config = Collection::fromConfig($config, $defaults, $required);
        $client = new static($config->get('base_url'), $config);
        $version = self::API_VERSION;
        $client->setDefaultHeaders(array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => "application/vnd.acquia-${version}+json"
        ));

        $plugin = new LockerAuthPlugin($config->get('username'), $config->get('password'));
        $client->addSubscriber($plugin);

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderParams()
    {
        return array(
            'base_url' => $this->getConfig('base_url'),
            'username' => $this->getConfig('username'),
            'password' => $this->getConfig('password'),
        );
    }

    /**
     * @param string $lock_id
     *
     * @return \Acquia\Lock\Response\Lock
     *
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     */
    public function getLock($lock_id)
    {
        $variables = array('lock_id' => $lock_id);
        $request = $this->get(array('{+base_path}/{lock_id}.json', $variables));
        try
        {
            return new Response\Lock($lock_id, $request);
        }
        catch (\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            if ($response->getStatusCode() == 404)
            {
                throw new Exception\LockNotFound("Lock '{$lock_id}' not found.");
            }
            throw $e;
        }
    }

    /**
     * @param string $lock_id
     * @param int    $ttl
     * @param string $message
     *
     * @return \Acquia\Lock\Response\Lock
     *
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     */
    public function acquireLock($lock_id, $ttl, $message, $timeout = 30)
    {
        $variables = array('lock_id' => $lock_id);
        $data = array('ttl' => $ttl, 'message' => $message);
        $request = $this->post(array('{+base_path}/{lock_id}.json', $variables), NULL, $data);
        if ($timeout > 0)
        {
            $backoffPlugin = new \Guzzle\Plugin\Backoff\BackoffPlugin(
                new \Acquia\Locker\Guzzle\Plugin\Backoff\TimeoutBackoffStrategy($timeout,
                    new \Guzzle\Plugin\Backoff\HttpBackoffStrategy(array(409, 500, 503),
                        new \Guzzle\Plugin\Backoff\ConstantBackoffStrategy(1)
                    )
                )
            );
            $request->addSubscriber($backoffPlugin);
        }
        try
        {
            return new Response\Lock($lock_id, $request);
        }
        catch (\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            if ($response->getStatusCode() == 409)
            {
                throw new Exception\LockAcquireTimeout("Could not acquire lock '{$lock_id}' in {$timeout} seconds.");
            }
            throw $e;
        }
    }

    /**
     * @param string $lock_id
     * @param string $uuid
     * @param int    $ttl
     *
     * @return \Acquia\Lock\Response\Lock
     *
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     */
    public function renewLock($lock_id, $uuid, $ttl)
    {
        $variables = array('lock_id' => $lock_id);
        $data = array('uuid' => $uuid, 'ttl' => $ttl);
        $request = $this->put(array('{+base_path}/{lock_id}.json', $variables), NULL, $data);
        try
        {
            return new Response\Lock($lock_id, $request);
        }
        catch (\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            if ($response->getStatusCode() == 409)
            {
                throw new Exception\LockUuidMismatch("Lock UUID mismatch.");
            }
            throw $e;
        }
    }

    /**
     * @param string $lock_id
     * @param string $uuid
     * @param bool   $force
     *
     * @return \Acquia\Lock\Response\Lock
     *
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     */
    public function releaseLock($lock_id, $uuid = NULL, $force = false)
    {
        $variables = array('lock_id' => $lock_id);
        $data = array('uuid' => $uuid, 'force' => $force);
        $request = $this->delete(array('{+base_path}/{lock_id}.json', $variables), NULL, $data);
        try
        {
            return new Response\Lock($lock_id, $request);
        }
        catch (\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            if ($response->getStatusCode() == 409)
            {
                throw new Exception\LockUuidMismatch("Lock UUID mismatch or Lock not found.");
            }
            throw $e;
        }
    }
}
