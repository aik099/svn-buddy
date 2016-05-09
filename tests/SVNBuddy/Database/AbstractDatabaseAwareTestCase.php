<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Database;


use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use ConsoleHelpers\SVNBuddy\Container;
use ConsoleHelpers\SVNBuddy\Database\StatementProfiler;

abstract class AbstractDatabaseAwareTestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * Database.
	 *
	 * @var ExtendedPdoInterface
	 */
	protected $database;

	protected function setUp()
	{
		parent::setUp();

		$this->database = $this->createDatabase();
	}

	/**
	 * Checks, that database table is empty.
	 *
	 * @param array $table_names Table names.
	 *
	 * @return void
	 */
	protected function assertTablesEmpty(array $table_names)
	{
		foreach ( $table_names as $table_name ) {
			$this->assertTableCount($table_name, 0);
		}
	}

	/**
	 * Checks, that database table is empty.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return void
	 */
	protected function assertTableEmpty($table_name)
	{
		$this->assertTableCount($table_name, 0);
	}

	/**
	 * Checks, table content.
	 *
	 * @param string $table_name       Table name.
	 * @param array  $expected_content Expected content.
	 *
	 * @return void
	 */
	protected function assertTableContent($table_name, array $expected_content)
	{
		$this->assertSame(
			$expected_content,
			$this->_dumpTable($table_name),
			'Table "' . $table_name . '" content isn\'t correct.'
		);
	}

	/**
	 * Returns contents of the table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return array
	 */
	private function _dumpTable($table_name)
	{
		return $this->database->fetchAll('SELECT * FROM ' . $table_name);
	}

	/**
	 * Checks, that database table contains given number of records.
	 *
	 * @param string  $table_name   Table name.
	 * @param integer $record_count Record count.
	 *
	 * @return void
	 */
	protected function assertTableCount($table_name, $record_count)
	{
		$sql = 'SELECT COUNT(*)
				FROM ' . $table_name;
		$this->assertEquals(
			$record_count,
			$this->database->fetchValue($sql),
			'The "' . $table_name . '" table contains ' . $record_count . ' records'
		);
	}

	/**
	 * Creates database for testing with correct db structure.
	 *
	 * @return ExtendedPdoInterface
	 */
	protected function createDatabase()
	{
		return new ExtendedPdo('sqlite::memory:');
	}

	/**
	 * Creates statement profiler.
	 *
	 * @return StatementProfiler
	 */
	protected function createStatementProfiler()
	{
		$container = new Container();

		return $container['statement_profiler'];
	}

}