<?php

WieldyCache_Core::loadLibrary('Engine');

class WieldyCache_File_Engine extends WieldyCache_Engine
{
	public $dir;

	public function __construct($params)
	{
		if (!isset($params['dir']))
			throw new WieldyCache_Exception('Dir not set');
		if (!is_dir($params['dir']))
			throw new WieldyCache_Exception('Cache dir nonexistent: '.$params['dir']);
		if (!is_writable($params['dir']))
			throw new WieldyCache_Exception('Cache dir not writable: '.$params['dir']);
		$this->dir = rtrim($params['dir'], DIRECTORY_SEPARATOR);
	}
	
    public function read($key) 
	{
        $data = @file_get_contents($this->getPathByKey($key));
		if (!$data)
			return null;
        $data = @unserialize($data);
		if (!$data)
			return null;

		list($value, $expire) = $data;
		if ($expire > 0 && $expire < time()) {
			$this->remove($key);
			return null;
		}

		return $value;
    }

	public function write($key, $data, $expire = null)
	{
		if (!is_numeric($expire))
			$expire = $this->defaultExpire;
		$file = $this->getPathByKey($key);
		if (!($fh = fopen($file, 'wb')))
			throw new WieldyCache_Exception('Error creating cache file (fopen failed): '.$file);
		flock($fh, LOCK_EX);
		fwrite($fh, serialize(array($data, time() + $expire)));
		flock($fh, LOCK_UN);
		fclose($fh);
	}

    private function getPathByKey($key)
	{
		if (is_array($key))
			$key = implode('_', $key);

		$cleanKey = $key;
		$cleanKey = strtolower($cleanKey);
		$cleanKey = preg_replace('/[^a-z0-9]/', '_', $cleanKey);
		while (strpos($cleanKey, '__') !== false)
			$cleanKey = str_replace('__', '_', $cleanKey);
		$cleanKey = trim($cleanKey, '_');
		$cleanKey = 'wc_'.$cleanKey;
		return $this->dir.'/'.$cleanKey.'_'.md5($key);
    }

	public function remove($key)
	{
		$file = $this->getPathByKey($key);
		if (is_file($file)) {
            if (!unlink($file))
                throw new Exception('Cache file not deleted: '.$file);
		}
	}
	
	public function removeAll()
	{
		$files = glob($this->dir.'/wc_*');
		foreach ($files as $file)
			unlink($file);
	}
}
