<?php

namespace Survos\GridGroupBundle\Service;


class CountableArrayCache
{
    /**
     * Cache data.
     *
     * @var array
     */
    protected $cache = [];

    public function count(): int
    {
        return count($this->cache);
    }

    public function getCache(): array
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    public function contains($key)
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->cache[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data): void
    {
        if ($key == 'inscription') {
            $key = $key;
            assert($key <> 'inscription', "Bad key " . $key);
        }
        $this->cache[$key] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->cache = [];
    }
}
