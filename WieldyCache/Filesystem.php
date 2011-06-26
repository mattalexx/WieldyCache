<?php

class WieldyCache_Filesystem
{
	function checkReadWriteAll($fs_node)
	{
		clearstatcache();
		$isReadable = is_readable($fs_node);
		$isWritable = (substr(sprintf('%o', fileperms($fs_node)), -4) === '0777');
		return ($isReadable && $isWritable);
	}

	function makeReadWriteAll($fs_node)
	{
		if (self::checkReadWriteAll($fs_node))
			return true;
		@chmod($fs_node, 0777);
		return self::checkReadWriteAll($fs_node);
	}

	function createDirectory($directory, $safe_dir)
	{
		// Parse offset directory (only sub nodes of the safe dir will be altered)
		if (strpos($directory, $safe_dir) !== 0)
			return false;
		$offset = strlen($safe_dir);

		// Define path pieces
		$base_dir = substr($directory, 0, $offset);
		$create_dir = substr($directory, $offset + 1);
		$create_dir_pieces = explode('/', $create_dir);

		// Create sub directories
		for ($i=0; $i<count($create_dir_pieces); $i++) {
			$this_dir = $base_dir.'/'.implode('/', array_slice($create_dir_pieces, 0, $i + 1));

			// Create it
			if (!is_dir($this_dir)) {
				mkdir($this_dir, 0777);
				if (!is_dir($this_dir))
					return false;
			}

			// Set its permissions
			if (!self::makeReadWriteAll($this_dir))
				return false;
		}
		return true;
	}

	function directoryExists($directory)
	{
		return is_dir($directory);
	}

	function checkDirectory($directory)
	{
		return (is_dir($directory) && self::checkReadWriteAll($directory));
	}

	function remove($dirname)
	{
		// Sanity check
		if (!file_exists($dirname))
			return false;

		// Simple delete for a file
		if (is_file($dirname) || is_link($dirname))
			return unlink($dirname);

		// Loop through the folder
		$dir = dir($dirname);
		while (false !== $entry = $dir->read()) {

			// Skip pointers
			if ($entry == '.' || $entry == '..')
				continue;

			// Recurse
			self::remove($dirname.'/'.$entry);
		}

		// Clean up
		$dir->close();
		return rmdir($dirname);
	}

	function emptyDirectory($dirname)
	{
		// Sanity check
		if (!file_exists($dirname))
			return false;

		// Remove each node within
		$globString = rtrim($dirname, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*';
		foreach (glob($globString) as $file) {
			self::remove($file);

			// For all we know
			return true;
		}
	}
