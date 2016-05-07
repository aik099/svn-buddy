<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Database;


use Aura\Sql\ProfilerInterface;
use ConsoleHelpers\ConsoleKit\ConsoleIO;

class StatementProfiler implements ProfilerInterface
{

	/**
	 * Is the profiler active?
	 *
	 * @var boolean
	 */
	protected $active = false;

	/**
	 * Retained profiles.
	 *
	 * @var array
	 */
	protected $profiles = array();

	/**
	 * Ignore statements.
	 *
	 * @var array
	 */
	protected $ignoreStatements = array(
		'SELECT LastRevision FROM PluginData WHERE Name = :name',
		'SELECT Id FROM Projects WHERE Path = :path',
	);

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Debug mode.
	 *
	 * @var boolean
	 */
	private $_debugMode = false;

	/**
	 * Creates statement profiler
	 *
	 * @param ConsoleIO $io Console IO.
	 */
	public function __construct(ConsoleIO $io = null)
	{
		$this->_io = $io;
		$this->_debugMode = isset($io) && $io->isVerbose();
	}

	/**
	 * Turns the profiler on and off.
	 *
	 * @param boolean $active True to turn on, false to turn off.
	 *
	 * @return void
	 */
	public function setActive($active)
	{
		$this->active = (bool)$active;
	}

	/**
	 * Is the profiler active?
	 *
	 * @return boolean
	 */
	public function isActive()
	{
		return (bool)$this->active;
	}

	/**
	 * Adds a profile entry.
	 *
	 * @param float  $duration    The query duration.
	 * @param string $function    The PDO method that made the entry.
	 * @param string $statement   The SQL query statement.
	 * @param array  $bind_values The values bound to the statement.
	 *
	 * @return void
	 * @throws \PDOException When duplicate statement is detected.
	 */
	public function addProfile(
		$duration,
		$function,
		$statement,
		array $bind_values = array()
	) {
		if ( !$this->isActive() || $function === 'prepare' || !$statement ) {
			return;
		}

		$normalized_statement = preg_replace('/\s+/', ' ', $statement);

		if ( in_array($normalized_statement, $this->ignoreStatements) ) {
			return;
		}

		$profile_key = md5('statement:' . $normalized_statement . ';bind_values:' . serialize($bind_values));

		if ( isset($this->profiles[$profile_key]) ) {
			$error_msg = 'Duplicate statement:' . PHP_EOL . $normalized_statement;
			$error_msg .= PHP_EOL . 'Bind Values:' . PHP_EOL . print_r($bind_values, true);

			throw new \PDOException($error_msg);
		}

		$this->profiles[$profile_key] = array(
			'duration' => $duration,
			'function' => $function,
			'statement' => $statement,
			'bind_values' => $bind_values,
		);

		if ( $this->_debugMode ) {
			$runtime = sprintf('%01.2f', $duration);
			$this->_io->writeln(array(
				'',
				'<debug>[db, ' . round($runtime, 2) . 's]: ' . $normalized_statement . '</debug>',
			));
		}
	}

	/**
	 * Returns all the profile entries.
	 *
	 * @return array
	 */
	public function getProfiles()
	{
		return $this->profiles;
	}

	/**
	 * Reset all the profiles
	 *
	 * @return void
	 */
	public function resetProfiles()
	{
		$this->profiles = array();
	}

}