<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Database\Migration;


use ConsoleHelpers\SVNBuddy\Database\Migration\AbstractMigrationRunner;
use ConsoleHelpers\SVNBuddy\Database\Migration\PhpMigrationRunner;

class PhpMigrationRunnerTest extends AbstractMigrationRunnerTest
{

	public function testGetFileExtension()
	{
		$this->assertEquals('php', $this->runner->getFileExtension());
	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage The "empty-migration.php" migration doesn't return a closure.
	 */
	public function testRunMalformedPHPMigration()
	{
		$this->runner->run($this->getFixture('empty-migration.php'), $this->context->reveal());
	}

	public function testRun()
	{
		$this->database->perform('test')->shouldBeCalled();

		$this->runner->run($this->getFixture('non-empty-migration.php'), $this->context->reveal());
	}

	public function testGetTemplate()
	{
		$expected = <<<EOT
<?php
use ConsoleHelpers\SVNBuddy\Database\Migration\MigrationContext;

return function (MigrationContext \$context) {
	// Write PHP code here.
};
EOT;
		$this->assertEquals($expected, $this->runner->getTemplate());
	}

	/**
	 * Creates migration type.
	 *
	 * @return AbstractMigrationRunner
	 */
	protected function createMigrationRunner()
	{
		return new PhpMigrationRunner();
	}

}
