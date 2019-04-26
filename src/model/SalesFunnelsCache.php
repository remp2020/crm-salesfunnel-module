<?php

namespace Crm\SalesFunnelModule;

use Nette\Utils\Json;
use Predis\Client;

class SalesFunnelsCache
{
    const REDIS_KEY = 'sales-funnels';

    /** @var Client */
    private $redis;

    private $host;

    private $port;

    private $db;

    public function __construct($host = '127.0.0.1', $port = 6379, $db = 0)
    {
        $this->host = $host ?? '127.0.0.1';
        $this->port = $port ?? 6379;
        $this->db = $db;
    }

    private function connect()
    {
        if (!$this->redis) {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => $this->host,
                'port'   => $this->port,
            ]);
            if ($this->db) {
                $this->redis->select($this->db);
            }
        }

        return $this->redis;
    }

    public function add($id, $urlKey)
    {
        $funnel = JSON::encode([
            'id' => $id,
            'url_key' => $urlKey,
        ]);
        return (bool)$this->connect()->hset(static::REDIS_KEY, $id, $funnel);
    }

    public function remove($id)
    {
        return $this->connect()->hdel(static::REDIS_KEY, $id);
    }

    public function all()
    {
        $data = $this->connect()->hgetall(static::REDIS_KEY);
        $res = [];
        foreach ($data as $record) {
            $res[] = JSON::decode($record);
        }
        return $res;
    }

    public function removeAll()
    {
        return $this->connect()->del([static::REDIS_KEY]);
    }
}
