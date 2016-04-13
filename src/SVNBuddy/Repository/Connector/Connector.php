<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository\Connector;


use ConsoleHelpers\ConsoleKit\ConsoleIO;
use ConsoleHelpers\SVNBuddy\Cache\CacheManager;
use ConsoleHelpers\ConsoleKit\Config\ConfigEditor;
use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Process\IProcessFactory;

/**
 * Executes command on the repository.
 */
class Connector
{

	const STATUS_UNVERSIONED = 'unversioned';

	/**
	 * Reference to configuration.
	 *
	 * @var ConfigEditor
	 */
	private $_configEditor;

	/**
	 * Process factory.
	 *
	 * @var IProcessFactory
	 */
	private $_processFactory;

	/**
	 * Console IO.
	 *
	 * @var ConsoleIO
	 */
	private $_io;

	/**
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private $_cacheManager;

	/**
	 * Path to an svn command.
	 *
	 * @var string
	 */
	private $_svnCommand = 'svn';

	/**
	 * Cache duration for next invoked command.
	 *
	 * @var mixed
	 */
	private $_nextCommandCacheDuration = null;

	/**
	 * Whatever to cache last repository revision or not.
	 *
	 * @var mixed
	 */
	private $_lastRevisionCacheDuration = null;

	/**
	 * Creates repository connector.
	 *
	 * @param ConfigEditor    $config_editor   ConfigEditor.
	 * @param IProcessFactory $process_factory Process factory.
	 * @param ConsoleIO       $io              Console IO.
	 * @param CacheManager    $cache_manager   Cache manager.
	 */
	public function __construct(
		ConfigEditor $config_editor,
		IProcessFactory $process_factory,
		ConsoleIO $io,
		CacheManager $cache_manager
	) {
		$this->_configEditor = $config_editor;
		$this->_processFactory = $process_factory;
		$this->_io = $io;
		$this->_cacheManager = $cache_manager;

		$cache_duration = $this->_configEditor->get('repository-connector.last-revision-cache-duration');

		if ( (string)$cache_duration === '' || substr($cache_duration, 0, 1) === '0' ) {
			$cache_duration = 0;
		}

		$this->_lastRevisionCacheDuration = $cache_duration;

		$this->prepareSvnCommand();
	}

	/**
	 * Prepares static part of svn command to be used across the script.
	 *
	 * @return void
	 */
	protected function prepareSvnCommand()
	{
		$username = $this->_configEditor->get('repository-connector.username');
		$password = $this->_configEditor->get('repository-connector.password');

		$this->_svnCommand .= ' --non-interactive';

		if ( $username ) {
			$this->_svnCommand .= ' --username ' . $username;
		}

		if ( $password ) {
			$this->_svnCommand .= ' --password ' . $password;
		}
	}

	/**
	 * Builds a command.
	 *
	 * @param string      $sub_command  Sub command.
	 * @param string|null $param_string Parameter string.
	 *
	 * @return Command
	 */
	public function getCommand($sub_command, $param_string = null)
	{
		$command_line = $this->buildCommand($sub_command, $param_string);

		$command = new Command(
			$this->_processFactory->createProcess($command_line, 1200),
			$this->_io,
			$this->_cacheManager
		);

		if ( isset($this->_nextCommandCacheDuration) ) {
			$command->setCacheDuration($this->_nextCommandCacheDuration);
			$this->_nextCommandCacheDuration = null;
		}

		return $command;
	}

	/**
	 * Builds command from given arguments.
	 *
	 * @param string $sub_command  Command.
	 * @param string $param_string Parameter string.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When command contains spaces.
	 */
	protected function buildCommand($sub_command, $param_string = null)
	{
		if ( strpos($sub_command, ' ') !== false ) {
			throw new \InvalidArgumentException('The "' . $sub_command . '" sub-command contains spaces.');
		}

		$command_line = $this->_svnCommand;

		if ( !empty($sub_command) ) {
			$command_line .= ' ' . $sub_command;
		}

		if ( !empty($param_string) ) {
			$command_line .= ' ' . $param_string;
		}

		$command_line = preg_replace_callback(
			'/\{([^\}]*)\}/',
			function (array $matches) {
				return escapeshellarg($matches[1]);
			},
			$command_line
		);

		return $command_line;
	}

	/**
	 * Sets cache configuration for next created command.
	 *
	 * @param mixed $cache_duration Cache duration.
	 *
	 * @return self
	 */
	public function withCache($cache_duration)
	{
		$this->_nextCommandCacheDuration = $cache_duration;

		return $this;
	}

	/**
	 * Returns property value.
	 *
	 * @param string $name        Property name.
	 * @param string $path_or_url Path to get property from.
	 * @param mixed  $revision    Revision.
	 *
	 * @return string
	 */
	public function getProperty($name, $path_or_url, $revision = null)
	{
		$param_string = $name . ' {' . $path_or_url . '}';

		if ( isset($revision) ) {
			$param_string .= ' --revision ' . $revision;
		}

		return $this->getCommand('propget', $param_string)->run();
	}

	/**
	 * Returns relative path of given path/url to the root of the repository.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return string
	 */
	public function getRelativePath($path_or_url)
	{
		$repository_root_url = $this->getRootUrl($path_or_url);
		$wc_url = (string)$this->_getSvnInfoEntry($path_or_url)->url;

		return preg_replace('/^' . preg_quote($repository_root_url, '/') . '/', '', $wc_url, 1);
	}

	/**
	 * Returns repository root url from given path/url.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return string
	 */
	public function getRootUrl($path_or_url)
	{
		return (string)$this->_getSvnInfoEntry($path_or_url)->repository->root;
	}

	/**
	 * Detects ref from given path.
	 *
	 * @param string $path Path to a file.
	 *
	 * @return string|boolean
	 */
	public function getRefByPath($path)
	{
		if ( preg_match('#^.*?/(trunk|branches/[^/]*|tags/[^/]*|releases/[^/]*).*$#', $path, $regs) ) {
			return $regs[1];
		}

		return false;
	}

	/**
	 * Returns URL of the working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return string
	 * @throws RepositoryCommandException When repository command failed to execute.
	 */
	public function getWorkingCopyUrl($wc_path)
	{
		if ( $this->isUrl($wc_path) ) {
			return $wc_path;
		}

		try {
			$wc_url = (string)$this->_getSvnInfoEntry($wc_path)->url;
		}
		catch ( RepositoryCommandException $e ) {
			if ( $e->getCode() == RepositoryCommandException::SVN_ERR_WC_UPGRADE_REQUIRED ) {
				$message = explode(PHP_EOL, $e->getMessage());

				$this->_io->writeln(array('', '<error>' . end($message) . '</error>', ''));

				if ( $this->_io->askConfirmation('Run "svn upgrade"', false) ) {
					$this->getCommand('upgrade', '{' . $wc_path . '}')->runLive();

					return $this->getWorkingCopyUrl($wc_path);
				}
			}

			throw $e;
		}

		return $wc_url;
	}

	/**
	 * Returns last changed revision on path/url.
	 *
	 * @param string $path_or_url Path or url.
	 *
	 * @return integer
	 */
	public function getLastRevision($path_or_url)
	{
		// Cache "svn info" commands to remote urls, not the working copy.
		$cache_duration = $this->isUrl($path_or_url) ? $this->_lastRevisionCacheDuration : null;

		return (int)$this->_getSvnInfoEntry($path_or_url, $cache_duration)->commit['revision'];
	}

	/**
	 * Determines if given path is in fact an url.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 */
	public function isUrl($path)
	{
		return strpos($path, '://') !== false;
	}

	/**
	 * Returns project url (container for "trunk/branches/tags/releases" folders).
	 *
	 * @param string $repository_url Repository url.
	 *
	 * @return string
	 */
	public function getProjectUrl($repository_url)
	{
		if ( preg_match('#^(.*?)/(trunk|branches|tags|releases).*$#', $repository_url, $regs) ) {
			return $regs[1];
		}

		return $repository_url;
	}

	/**
	 * Returns "svn info" entry for path or url.
	 *
	 * @param string $path_or_url    Path or url.
	 * @param mixed  $cache_duration Cache duration.
	 *
	 * @return \SimpleXMLElement
	 * @throws \LogicException When unexpected 'svn info' results retrieved.
	 */
	private function _getSvnInfoEntry($path_or_url, $cache_duration = null)
	{
		// Cache "svn info" commands to remote urls, not the working copy.
		if ( !isset($cache_duration) && $this->isUrl($path_or_url) ) {
			$cache_duration = '1 year';
		}

		$svn_info = $this->withCache($cache_duration)->getCommand('info', '--xml {' . $path_or_url . '}')->run();

		// When getting remote "svn info", then path is last folder only.
		if ( basename($this->_getSvnInfoEntryPath($svn_info->entry)) != basename($path_or_url) ) {
			throw new \LogicException('The directory "' . $path_or_url . '" not found in "svn info" command results.');
		}

		return $svn_info->entry;
	}

	/**
	 * Returns path of "svn info" entry.
	 *
	 * @param \SimpleXMLElement $svn_info_entry The "entry" node of "svn info" command.
	 *
	 * @return string
	 */
	private function _getSvnInfoEntryPath(\SimpleXMLElement $svn_info_entry)
	{
		// SVN 1.7+.
		$path = (string)$svn_info_entry->{'wc-info'}->{'wcroot-abspath'};

		if ( $path ) {
			return $path;
		}

		// SVN 1.6-.
		return (string)$svn_info_entry['path'];
	}

	/**
	 * Returns revision, when path was added to repository.
	 *
	 * @param string $url Url.
	 *
	 * @return integer
	 * @throws \InvalidArgumentException When not an url was given.
	 */
	public function getFirstRevision($url)
	{
		if ( !$this->isUrl($url) ) {
			throw new \InvalidArgumentException('The repository URL "' . $url . '" is invalid.');
		}

		$log = $this->withCache('1 year')->getCommand('log', ' -r 1:HEAD --limit 1 --xml {' . $url . '}')->run();

		return (int)$log->logentry['revision'];
	}

	/**
	 * Returns conflicts in working copy.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function getWorkingCopyConflicts($wc_path)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path) as $path => $status ) {
			if ( $status['item'] == 'conflicted' || $status['props'] == 'conflicted' || $status['tree-conflicted'] ) {
				$ret[] = $path;
			}
		}

		return $ret;
	}

	/**
	 * Returns compact working copy status.
	 *
	 * @param string  $wc_path          Working copy path.
	 * @param boolean $with_unversioned With unversioned.
	 *
	 * @return string
	 */
	public function getCompactWorkingCopyStatus($wc_path, $with_unversioned = true)
	{
		$ret = array();

		foreach ( $this->getWorkingCopyStatus($wc_path) as $path => $status ) {
			if ( !$with_unversioned && $status['item'] == self::STATUS_UNVERSIONED ) {
				continue;
			}

			$line = $this->getShortItemStatus($status['item']) . $this->getShortPropertiesStatus($status['props']);
			$line .= '   ' . $path;

			$ret[] = $line;
		}

		return implode(PHP_EOL, $ret);
	}

	/**
	 * Returns short item status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When unknown status given.
	 */
	protected function getShortItemStatus($status)
	{
		$status_map = array(
			'added' => 'A',
			'conflicted' => 'C',
			'deleted' => 'D',
			'external' => 'X',
			'ignored' => 'I',
			// 'incomplete' => '',
			// 'merged' => '',
			'missing' => '!',
			'modified' => 'M',
			'none' => ' ',
			'normal' => '_',
			// 'obstructed' => '',
			'replaced' => 'R',
			'unversioned' => '?',
		);

		if ( !isset($status_map[$status]) ) {
			throw new \InvalidArgumentException('The "' . $status . '" item status is unknown.');
		}

		return $status_map[$status];
	}

	/**
	 * Returns short item status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When unknown status given.
	 */
	protected function getShortPropertiesStatus($status)
	{
		$status_map = array(
			'conflicted' => 'C',
			'modified' => 'M',
			'normal' => '_',
			'none' => ' ',
		);

		if ( !isset($status_map[$status]) ) {
			throw new \InvalidArgumentException('The "' . $status . '" properties status is unknown.');
		}

		return $status_map[$status];
	}

	/**
	 * Returns working copy status.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	protected function getWorkingCopyStatus($wc_path)
	{
		$ret = array();
		$status = $this->getCommand('status', '--xml {' . $wc_path . '}')->run();

		foreach ( $status->target as $target ) {
			if ( (string)$target['path'] !== $wc_path ) {
				continue;
			}

			foreach ( $target as $entry ) {
				$path = (string)$entry['path'];

				if ( $path === $wc_path ) {
					$path = '.';
				}
				else {
					$path = str_replace($wc_path . '/', '', $path);
				}

				$ret[$path] = array(
					'item' => (string)$entry->{'wc-status'}['item'],
					'props' => (string)$entry->{'wc-status'}['props'],
					'tree-conflicted' => (string)$entry->{'wc-status'}['tree-conflicted'] === 'true',
				);
			}
		}

		return $ret;
	}

	/**
	 * Determines if working copy contains mixed revisions.
	 *
	 * @param string $wc_path Working copy path.
	 *
	 * @return array
	 */
	public function isMixedRevisionWorkingCopy($wc_path)
	{
		$revisions = array();
		$status = $this->getCommand('status', '--xml --verbose {' . $wc_path . '}')->run();

		foreach ( $status->target as $target ) {
			if ( (string)$target['path'] !== $wc_path ) {
				continue;
			}

			foreach ( $target as $entry ) {
				$item_status = (string)$entry->{'wc-status'}['item'];

				if ( $item_status !== self::STATUS_UNVERSIONED ) {
					$revision = (int)$entry->{'wc-status'}['revision'];
					$revisions[$revision] = true;
				}
			}
		}

		return count($revisions) > 1;
	}

	/**
	 * Determines if there is a working copy on a given path.
	 *
	 * @param string $path Path.
	 *
	 * @return boolean
	 * @throws \InvalidArgumentException When path isn't found.
	 * @throws RepositoryCommandException When repository command failed to execute.
	 */
	public function isWorkingCopy($path)
	{
		if ( $this->isUrl($path) || !file_exists($path) || !is_dir($path) ) {
			throw new \InvalidArgumentException('Path "' . $path . '" not found or isn\'t a directory.');
		}

		try {
			$wc_url = $this->getWorkingCopyUrl($path);
		}
		catch ( RepositoryCommandException $e ) {
			if ( $e->getCode() == RepositoryCommandException::SVN_ERR_WC_NOT_WORKING_COPY ) {
				return false;
			}

			throw $e;
		}

		return $wc_url != '';
	}

}
