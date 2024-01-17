<?php

namespace Crm\ProductsModule\Models;

use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Nette\Utils\Json;

class TagsCache
{
    use RedisClientTrait;

    const REDIS_KEY = 'tags';

    public function __construct(RedisClientFactory $redisClientFactory)
    {
        $this->redisClientFactory = $redisClientFactory;
    }

    public function add($id, $code)
    {
        $tag = Json::encode([
            'id' => $id,
            'code' => $code,
        ]);
        return (bool)$this->redis()->hset(static::REDIS_KEY, $id, $tag);
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
            $res[] = Json::decode($record);
        }
        return $res;
    }

    public function removeAll()
    {
        return $this->redis()->del([static::REDIS_KEY]);
    }
}
