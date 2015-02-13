<?php

namespace Acquia\Locker\Response;

class Lock extends \Acquia\Rest\Element
{
    // protected $client;

    /**
     * @param string|array|\Guzzle\Http\Message\Request $array
     */
    public function __construct($lock_id, $data)
    {
        parent::__construct($data);
        $this['lock_id'] = $lock_id;
        $this->setIdColumn('lock_id');
    }

    /**
     * @return string
     */
    public function lock_id()
    {
        return $this['lock_id'];
    }

    /**
     * @return string
     */
    public function status()
    {
        return $this['status'];
    }

    /**
     * @return array
     */
    public function data($field = NULL)
    {
        if (isset($field))
        {
            if (isset($this['data'][$field]))
            {
                return $this['data'][$field];
            }
            else
            {
                return NULL;
            }
        }
        return $this['data'];
    }

    // public function renewLock(int $ttl)
    // {
    //     if (!isset($this->client) || !($this->client instanceof \Acquia\Locker\LockerClient))
    //     {
    //         throw new LockNoLockerClient('');
    //     }
    //     $this->client->renewLock($this->lock_id(), $this->uuid(), $ttl);
    // }
}
