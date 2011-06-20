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
		if (!in_array($engine, array('Memcache', 'File'))) {
			throw new WieldyCache_Exception('Invalid engine type: '.$engine);
		}
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
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
	
	public static function read($key)
	{
		$self = self::getInstance();
		if (!$self->readEnabled) {
			return null;
		}
		return $self->engine->read($key);
	}
	
	public static function cacheOrCall($cacheKey, $expires, $function, $params = array())
	{
		if (is_null($data = WieldyCache::read($cacheKey))) {
			$data = call_user_func_array($function, (array) $params);
			WieldyCache::write($cacheKey, $data, $expires);
		}
		return $data;
	}
	
	public static function write($key, $data, $expire = null)
	{
		$self = self::getInstance();
		if (!$self->writeEnabled) {
			return null;
		}
		return $self->engine->write($key, $data, $expire);
	}
	
	public static function remove($key = null)
	{
		$self = self::getInstance();
		if (!is_null($key)) {
			return $self->engine->remove($key);
		}
		return $self->engine->removeAll();
	}
	
	public static function removeNamespace($nsKey)
	{
		$self = self::getInstance();
		return $self->engine->removeNamespace($nsKey);
	}

	private function __clone() {}
	
/**
 * This is here to easily disable a cache call during development
 *
 */
	public static function readd($key)
	{
		return null;
	}
	
/**
 * This is here to easily disable a cache call during development
 *
 */
	public static function cacheOrCalld($cacheKey, $expires, $function, $params = array())
	{
		$data = call_user_func_array($function, (array) $params);
		WieldyCache::write($cacheKey, $data, $expires);
		return $data;
	}
}
