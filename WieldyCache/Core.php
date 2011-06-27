<?php

WieldyCache_Core::loadLibrary('Exception');

class WieldyCache_Core
{
	protected static $instance;
	public $libDir;
	public $engine;
	public $readEnabled = true;
	public $writeEnabled = true;

	private function __construct()
	{
		$this->libDir = realpath(dirname(__FILE__).'/..');
	}
	
	public function useFileEngine($dir) 
	{
		$this->setEngine('File', array('dir' => $dir));
	}
	
	public function useMemcacheEngine() 
	{
		$this->setEngine('Memcache');
	}

	public function setEngine($engine, $params = null)
	{
		if (!in_array($engine, array('Memcache', 'File')))
			throw new WieldyCache_Exception('Invalid engine type: '.$engine);
		self::loadLibrary('Engine/'.$engine);
		$self = self::getInstance();
		$className = 'WieldyCache_'.$engine.'_Engine';
		$self->engine = new $className($params);
	}

	public static function loadLibrary($name)
	{
		$self = self::getInstance();
		require_once $self->libDir.'/WieldyCache/'.$name.'.php';
	}

	public static function getInstance()
	{
		if (!isset(self::$instance))
			self::$instance = new self();
		return self::$instance;
	}
	
	public static function read($key)
	{
		// Pass false into the first param to disable temporarily
		if ($key === false)
			return null;

		$self = self::getInstance();
		if (!$self->readEnabled)
			return null;
		return $self->engine->read($key);
	}
	
	public static function methodCache($expires = null,
			$extraCacheKeyParameters = null)
	{
		// Pass false into the first param to disable temporarily
		if ($expires === false)
			return null;

		$debugBacktrace = debug_backtrace();
		$backtrace = $debugBacktrace[1];

		if (!is_numeric($expires))
			$expires = 60*60*12; // 12 hours

		$cacheKey = array('methodcache_'.$backtrace['class'], $backtrace['function']);
		if ($backtrace['args']) {
			$hash = md5(var_export($backtrace['args'], true));
			$cacheKey[1] .= '_'.count($backtrace['args']).'args'.$hash;
		}
		if (!empty($extraCacheKeyParameters)) {
			if (is_array($extraCacheKeyParameters))
				$extraCacheKeyParameters = implode('|', $extraCacheKeyParameters);
			$cacheKey[1] .= '_'.md5($extraCacheKeyParameters);
		}

		$flag = 'methodCacheCalling_'.md5(var_export($cacheKey, true));

		if (isset($GLOBALS[$flag]))
			return null;

		$data = WieldyCache::read($cacheKey);
		if (is_null($data)) {
			$GLOBALS[$flag] = true;
			$data = self::callFromBacktrace($backtrace);
			unset($GLOBALS[$flag]);
			WieldyCache::write($cacheKey, $data, $expires);
		}
		return $data;
	}
	
	public static function callFromBacktrace($backtrace)
	{
		$function = array($backtrace['object'], $backtrace['function']);
		$args = $backtrace['args'];
		$data = call_user_func_array($function, $args);
		return $data;
	}
	
	public static function write($key, $data, $expire = null)
	{
		$self = self::getInstance();
		if (!$self->writeEnabled)
			return null;
		return $self->engine->write($key, $data, $expire);
	}
	
	public static function remove($key = null)
	{
		$self = self::getInstance();
		if (!is_null($key))
			return $self->engine->remove($key);
		return $self->engine->removeAll();
	}
	
	public static function removeNamespace($nsKey)
	{
		$self = self::getInstance();
		return $self->engine->removeNamespace($nsKey);
	}

	private function __clone() {}
}
