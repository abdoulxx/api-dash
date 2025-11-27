<?php

namespace App\Support;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use DateInterval;

class CacheTagger
{
    /**
     * Return a cache repository supporting tags, falling back when the store doesn't.
     *
     * @param  array<int, string>  $tags
     */
    public static function tags(array $tags): Repository
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags($tags);
        }

        return new CacheTaggerFallback($tags);
    }
}

class CacheTaggerFallback implements Repository
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(private array $tags)
    {
        $this->tags = array_values(array_filter($tags));
    }

    public function has(string $key): bool
    {
        return Cache::has($this->taggedKey($key));
    }

    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->taggedKey($key), $default);
    }

    public function pull($key, $default = null)
    {
        $prefixed = $this->taggedKey($key);

        return Cache::pull($prefixed, $default);
    }

    public function put($key, $value, $ttl = null)
    {
        return Cache::put($this->taggedKey($key), $value, $ttl);
    }

    public function add($key, $value, $ttl = null)
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $ttl);
    }

    public function increment($key, $value = 1)
    {
        $prefixed = $this->taggedKey($key);

        return Cache::increment($prefixed, $value);
    }

    public function decrement($key, $value = 1)
    {
        $prefixed = $this->taggedKey($key);

        return Cache::decrement($prefixed, $value);
    }

    public function forever($key, $value)
    {
        return Cache::forever($this->taggedKey($key), $value);
    }

    public function remember($key, $ttl, $callback)
    {
        // Si le store supporte les tags nativement, utiliser Cache::tags()
        // Sinon, utiliser taggedKey() comme fallback
        $store = Cache::getStore();
        if ($store instanceof \Illuminate\Cache\TaggableStore) {
            // Utiliser les tags natifs de Laravel
            return Cache::tags($this->tags)->remember($key, $ttl, $callback);
        }
        
        // Fallback: utiliser taggedKey()
        return Cache::remember($this->taggedKey($key), $ttl, $callback);
    }

    public function sear($key, $callback)
    {
        return $this->rememberForever($key, $callback);
    }

    public function rememberForever($key, $callback)
    {
        return Cache::rememberForever($this->taggedKey($key), $callback);
    }

    public function forget($key)
    {
        return Cache::forget($this->taggedKey($key));
    }

    public function flush(): bool
    {
        Cache::flush();

        return true;
    }

    public function clear(): bool
    {
        return $this->flush();
    }

    public function getStore()
    {
        return Cache::getStore();
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return Cache::put($this->taggedKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return Cache::forget($this->taggedKey($key));
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }

        return true;
    }

    public function many(array $keys)
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    public function putMany(array $values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
    }

    private function taggedKey(string $key): string
    {
        if (empty($this->tags)) {
            return $key;
        }

        return implode('|', $this->tags).':'.$key;
    }
}







