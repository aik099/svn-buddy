<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\ConsoleHelpers\SVNBuddy\Repository\Connector;


use ConsoleHelpers\SVNBuddy\Repository\Connector\Command;
use Prophecy\Prophecy\ObjectProphecy;

class CommandTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * Process.
	 *
	 * @var ObjectProphecy
	 */
	private $_process;

	/**
	 * Console IO.
	 *
	 * @var ObjectProphecy
	 */
	private $_io;

	/**
	 * Cache manager.
	 *
	 * @var ObjectProphecy
	 */
	private $_cacheManager;

	/**
	 * Command.
	 *
	 * @var Command
	 */
	private $_command;

	protected function setUp()
	{
		parent::setUp();

		$this->_process = $this->prophesize('Symfony\\Component\\Process\\Process');
		$this->_io = $this->prophesize('ConsoleHelpers\\ConsoleKit\\ConsoleIO');
		$this->_cacheManager = $this->prophesize('ConsoleHelpers\\SVNBuddy\\Cache\\CacheManager');

		$this->_command = $this->_createCommand();
	}

	public function testRunWithoutCallbackAndWithoutCallback()
	{
		$this->_process->getCommandLine()->willReturn('svn log')->shouldBeCalled();
		$this->_process->mustRun(null)->shouldBeCalled();
		$this->_process->getOutput()->willReturn('OK')->shouldBeCalled();

		$this->_io->isVerbose()->willReturn(false);

		$this->assertEquals('OK', $this->_command->run());
	}

	/**
	 * Creates command.
	 *
	 * @return Command
	 */
	private function _createCommand()
	{
		return new Command($this->_process->reveal(), $this->_io->reveal(), $this->_cacheManager->reveal());
	}

}