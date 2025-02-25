<?php

namespace local_eventocoursecreation;

use cache;

/**
 * Caching implementation
 */
class EventoCache
{
    /**
     * @var cache The cache instance
     */
    private cache $cache;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache = cache::make('local_eventocoursecreation', 'coursecreation');
    }

    /**
     * Retrieves a value from the cache
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->cache->get($key);
    }

    /**
     * Sets a value in the cache
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->cache->set($key, $value);
    }

    /**
     * Deletes a value from the cache
     *
     * @param string $key
     */
    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }
}
