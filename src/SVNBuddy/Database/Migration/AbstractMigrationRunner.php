<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Database\Migration;


abstract class AbstractMigrationRunner
{

	/**
	 * Returns supported file extension.
	 *
	 * @return string
	 */
	abstract public function getFileExtension();

	/**
	 * Runs the migration.
	 *
	 * @param string           $migration_file Migration file.
	 * @param MigrationContext $context        Migration context.
	 *
	 * @return void
	 */
	abstract public function run($migration_file, MigrationContext $context);

	/**
	 * Returns new migration template.
	 *
	 * @return string
	 */
	public function getTemplate()
	{
		return '';
	}

}