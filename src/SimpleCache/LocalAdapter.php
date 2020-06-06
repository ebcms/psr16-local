<?php

namespace Ebcms\SimpleCache;

use Ebcms\SimpleCache\Exception\CacheException;
use Ebcms\SimpleCache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

class LocalAdapter implements CacheInterface
{

    private $cache_dir;

    public function __construct(string $cache_dir)
    {
        $this->cache_dir = $cache_dir;
        if (!is_dir($this->cache_dir)) {
            if (!@mkdir($this->cache_dir, 0755, true)) {
                throw new CacheException('No permission cache_dir:' . $cache_dir);
            }
        }
    }

    public function get($key, $default = null)
    {
        try {
            $file = $this->getCacheFile($key);
            if (!file_exists($file)) {
                return $default;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return $default;
            }
        } catch (\Throwable $th) {
            return $default;
        }
        return $cache['value'];
    }

    public function set($key, $value, $ttl = null)
    {
        try {
            $cache = [
                'key' => $key,
                'ttl' => $ttl ? time() + $ttl : 9999999999,
                'value' => $value,
            ];
            return file_put_contents($this->getCacheFile($key), serialize($cache));
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function delete($key)
    {
        try {
            $file = $this->getCacheFile($key);
            if (file_exists($file)) {
                return unlink($file);
            }
        } catch (\Throwable $th) {
            return false;
        }
        return true;
    }

    public function clear()
    {
        try {
            $tmp = scandir($this->cache_dir);
            foreach ($tmp as $val) {
                if ($val != '.' && $val != '..') {
                    if (is_dir($this->cache_dir . '/' . $val)) {
                        if (!rmdir($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    } else {
                        if (!unlink($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            return false;
        }
        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    public function has($key)
    {
        try {
            $file = $this->getCacheFile($key);
            if (!file_exists($file)) {
                return false;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return false;
            }
        } catch (\Throwable $th) {
            return false;
        }
        return true;
    }

    private function getCacheFile($key)
    {
        $this->validateKey($key);
        return $this->cache_dir . '/' . $key;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Key should be a non empty string');
        }

        $unsupportedMatched = preg_match('#[' . preg_quote('{}()/\@:') . ']#', $key);
        if ($unsupportedMatched > 0) {
            throw new InvalidArgumentException('Can\'t validate the specified key');
        }

        return true;
    }
}
