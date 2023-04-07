<?php

namespace Survos\GridGroupBundle\Service\Bedrock;

use Flintstone\Cache\ArrayCache;
use Flintstone\Cache\CacheInterface;

class CountableArrayCache extends ArrayCache implements CacheInterface
{
    public function count(): int
    {
        return count($this->cache);
    }

    public function getCache(): array
    {
        return $this->cache;
    }

}
