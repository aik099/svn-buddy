<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Cache;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Helper\SizeHelper;

class CacheManager
{

	/**
	 * Working directory.
	 *
	 * @var string
	 */
	private $_workingDirectory;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Size helper.
	 *
	 * @var SizeHelper
	 */
	private $_sizeHelper;

	/**
	 * Create cache manager.
	 *
	 * @param string     $working_directory Working directory.
	 * @param SizeHelper $size_helper       Size helper.
	 * @param ConsoleIO  $io                Console IO.
	 */
	public function __construct($working_directory, SizeHelper $size_helper, ConsoleIO $io = null)
	{
		$this->_workingDirectory = $working_directory;
		$this->_sizeHelper = $size_helper;
		$this->_io = $io;
	}

	/**
	 * Sets value in cache.
	 *
	 * @param string  $name        Name.
	 * @param mixed   $value       Value.
	 * @param mixed   $invalidator Invalidator.
	 * @param integer $duration    Duration in seconds.
	 *
	 * @return void
	 */
	public function setCache($name, $value, $invalidator = null, $duration = null)
	{
		$duration = $this->durationIntoSeconds($duration);

		$storage = $this->_getStorage($name, $duration);
		$storage->set(array(
			'name' => $name,
			'invalidator' => $invalidator,
			'duration' => $duration,
			'expiration' => $duration ? time() + $duration : null,
			'data' => $value,
		));
	}

	/**
	 * Gets value from cache.
	 *
	 * @param string  $name        Name.
	 * @param mixed   $invalidator Invalidator.
	 * @param integer $duration    Duration in seconds.
	 *
	 * @return mixed
	 */
	public function getCache($name, $invalidator = null, $duration = null)
	{
		$storage = $this->_getStorage($name, $this->durationIntoSeconds($duration));
		$cache = $storage->get();

		if ( !is_array($cache) || $cache['invalidator'] !== $invalidator ) {
			$storage->invalidate();

			return null;
		}

		if ( $cache['expiration'] && $cache['expiration'] < time() ) {
			$storage->invalidate();

			return null;
		}

		return $cache['data'];
	}

	/**
	 * Converts duration into seconds.
	 *
	 * @param integer $duration Duration in seconds.
	 *
	 * @return integer|null
	 */
	protected function durationIntoSeconds($duration = null)
	{
		if ( !isset($duration) ) {
			return null;
		}

		if ( is_numeric($duration) ) {
			$duration .= ' seconds';
		}

		$now = time();

		return strtotime('+' . $duration, $now) - $now;
	}

	/**
	 * Returns file-based cache storage.
	 *
	 * @param string  $name     Cache name.
	 * @param integer $duration Duration in seconds.
	 *
	 * @return ICacheStorage
	 * @throws \InvalidArgumentException When namespace is missing in the name.
	 */
	private function _getStorage($name, $duration = null)
	{
		$name_parts = explode(':', $name, 2);

		if ( count($name_parts) != 2 ) {
			throw new \InvalidArgumentException('The $name parameter must be in "namespace:name" format.');
		}

		$filename_parts = array(
			$name_parts[0],
			substr(hash_hmac('sha1', $name_parts[1], 'svn-buddy'), 0, 8),
			'D' . (isset($duration) ? $duration : 'INF'),
		);

		$cache_filename = $this->_workingDirectory . DIRECTORY_SEPARATOR . implode('_', $filename_parts) . '.cache';

		if ( isset($this->_io) && $this->_io->isVerbose() ) {
			$message = $cache_filename;

			if ( file_exists($cache_filename) ) {
				$message .= ' (hit: ' . $this->_sizeHelper->formatSize(filesize($cache_filename)) . ')';
			}
			else {
				$message .= ' (miss)';
			}

			$this->_io->writeln(array(
				'',
				'<debug>[cache]: ' . $message . '</debug>',
			));
		}

		return new FileCacheStorage($cache_filename);
	}

}
