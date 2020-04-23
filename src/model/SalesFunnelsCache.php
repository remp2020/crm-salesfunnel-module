<?php

namespace Crm\SalesFunnelModule;

use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Nette\Utils\Json;

class SalesFunnelsCache
{
    use RedisClientTrait;

    const REDIS_KEY = 'sales-funnels';

    public function __construct(RedisClientFactory $redisClientFactory)
    {
        $this->redisClientFactory = $redisClientFactory;
    }

    public function add($id, $urlKey)
    {
        $funnel = JSON::encode([
            'id' => $id,
            'url_key' => $urlKey,
        ]);
        return (bool)$this->redis()->hset(static::REDIS_KEY, $id, $funnel);
    }

    public function remove($id)
    {
        return $this->redis()->hdel(static::REDIS_KEY, $id);
    }

    public function all()
    {
        $data = $this->redis()->hgetall(static::REDIS_KEY);
        $res = [];
        foreach ($data as $record) {
            $res[] = JSON::decode($record);
        }
        return $res;
    }

    public function removeAll()
    {
        return $this->redis()->del([static::REDIS_KEY]);
    }
}
