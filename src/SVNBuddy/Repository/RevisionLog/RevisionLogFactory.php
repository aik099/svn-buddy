<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\RevisionLog;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;
use ConsoleHelpers\SVNBuddy\Repository\Parser\LogMessageParser;

class RevisionLogFactory
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Create revision log.
	 *
	 * @param Connector    $repository_connector Repository connector.
	 * @param CacheManager $cache_manager        Cache manager.
	 */
	public function __construct(
		Connector $repository_connector,
		CacheManager $cache_manager
	) {
		$this->_repositoryConnector = $repository_connector;
		$this->_cacheManager = $cache_manager;
	}

	/**
	 * Returns revision log for url.
	 *
	 * @param string    $repository_url Repository url.
	 * @param ConsoleIO $io             Console IO.
	 *
	 * @return RevisionLog
	 */
	public function getRevisionLog($repository_url, ConsoleIO $io = null)
	{
		$bugtraq_logregex = $this->_repositoryConnector->withCache('1 year')->getProperty(
			'bugtraq:logregex',
			$repository_url
		);

		$revision_log = new RevisionLog(
			$repository_url,
			$this->_repositoryConnector,
			$this->_cacheManager,
			$io
		);

		$revision_log->registerPlugin(new SummaryRevisionLogPlugin());
		$revision_log->registerPlugin(new PathsRevisionLogPlugin());
		$revision_log->registerPlugin(new BugsRevisionLogPlugin(new LogMessageParser($bugtraq_logregex)));
		$revision_log->registerPlugin(new MergesRevisionLogPlugin());
		$revision_log->registerPlugin(new RefsRevisionLogPlugin($this->_repositoryConnector));
		$revision_log->refresh();

		return $revision_log;
	}

}
