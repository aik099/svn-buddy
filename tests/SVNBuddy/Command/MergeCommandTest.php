<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Command;


use Mockery as m;

class MergeCommandTest extends AbstractCommandTestCase
{

	protected function setUp()
	{
		$this->commandName = 'merge';

		parent::setUp();
	}

	public function testExampleTest()
	{
		$this->markTestIncomplete('TODO');
	}
}
