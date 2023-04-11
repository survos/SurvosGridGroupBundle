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
    public function set($key, $data)
    {
        $this->cache[$key] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->cache = [];
    }
}
