<?php

WieldyCache_Core::loadLibrary('Engine');

class WieldyCache_Memcache_Engine extends WieldyCache_Engine
{
    private $memcache = null;
    public $host = 'localhost';
    public $port = 11211;
    public $persistent = true;

	public function __construct($params)
	{
		if (!class_exists('Memcache')) {
			throw new WieldyCache_Exception('Memcache not available. Is it installed?');
		}
		foreach (array('host', 'port', 'persistent') as $key) {
			if (isset($params[$key])) {
				$this->$key = $params[$key];
			}
		}
        $this->memcache = new Memcache;
		if ($this->persistent) {
			$connection = $this->memcache->pconnect($this->host, $this->port);
		} else {
			$connection = $this->memcache->connect($this->host, $this->port);
		}
		if (!$connection) {
			throw new Exception('Failed to connect to memcached server');
		}
	}

    public function read($key)
	{
		$cacheKey = $this->getCacheKey($key);
		$result = $this->memcache->get($cacheKey);
		return $result !== false ? $result : null;
    }

	public function write($key, $data, $expire = null)
	{
		if (!is_numeric($expire)) {
			$expire = $this->defaultExpire;
		}
		
		$cacheKey = $this->getCacheKey($key);
		
		// PECL Bug: http://pecl.php.net/bugs/bug.php?id=14239
		// Workaround: http://stackoverflow.com/questions/3697297/seemingly-impossible-php-variable-referencing-behavior-when-using-memcacheds-set
		$dataPassPre =& $data;
		$dataPass = $data;
		unset($dataPassPre);
		
		// Compression must be on for memcached 5.2.5 (PECL Bug: http://pecl.php.net/bugs/bug.php?id=14044)
		$this->memcache->set($cacheKey, $dataPass, MEMCACHE_COMPRESSED, time() + $expire);
	}

	public function getCacheKey($key)
	{
		if (!is_array($key)) {
			return $key;
		}
		$namespaceId = $this->getNamespaceId($key[0]);
		$cacheKey = $key[1].'_'.$namespaceId;
		return $cacheKey;
	}

	public function getNamespaceCacheKey($key)
	{
		return 'NS_'.$key;
	}

	public function getNamespaceId($key)
	{
		$nsCacheKey = $this->getNamespaceCacheKey($key);
		$nsId = $this->memcache->get($nsCacheKey);
		if (empty($nsId)) {
			$nsId = rand(1, 10000);
			$this->memcache->set($nsCacheKey, $nsId);
		}
		return $nsId;
	}

	public function removeNamespace($key)
	{
		$nsCacheKey = $this->getNamespaceCacheKey($key);
		$this->memcache->increment($nsCacheKey);
	}

	public function remove($key)
	{
		$cacheKey = $this->getCacheKey($key);
		$this->memcache->delete($cacheKey);
	}

	public function removeAll()
	{
        $this->memcache->flush();
	}
}
