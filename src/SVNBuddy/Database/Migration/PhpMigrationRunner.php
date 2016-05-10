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


class PhpMigrationRunner extends AbstractMigrationRunner
{

	/**
	 * Returns supported file extension.
	 *
	 * @return string
	 */
	public function getFileExtension()
	{
		return 'php';
	}

	/**
	 * Runs the migration.
	 *
	 * @param string           $migration_file Migration file.
	 * @param MigrationContext $context        Migration context.
	 *
	 * @return void
	 * @throws \LogicException When migration doesn't contain a closure.
	 */
	public function run($migration_file, MigrationContext $context)
	{
		$closure = require $migration_file;

		if ( !is_callable($closure) ) {
			throw new \LogicException('The "' . basename($migration_file) . '" migration doesn\'t return a closure.');
		}

		call_user_func($closure, $context);
	}

	/**
	 * Returns new migration template.
	 *
	 * @return string
	 */
	public function getTemplate()
	{
		return <<<EOT
<?php
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;

return function (MigrationContext \$context) {
	// Write PHP code here.
};
EOT;
	}

}
