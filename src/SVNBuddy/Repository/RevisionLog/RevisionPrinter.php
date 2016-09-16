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


use ConsoleHelpers\SVNBuddy\Helper\DateHelper;
use ConsoleHelpers\SVNBuddy\Helper\OutputHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class RevisionPrinter
{

	const COLUMN_DETAILS = 1;

	const COLUMN_SUMMARY = 2;

	const COLUMN_REFS = 3;

	const COLUMN_MERGE_ORACLE = 4;

	const COLUMN_MERGE_STATUS = 5;

	/**
	 * Date helper.
	 *
	 * @var DateHelper
	 */
	private $_dateHelper;

	/**
	 * Output helper.
	 *
	 * @var OutputHelper
	 */
	private $_outputHelper;

	/**
	 * Columns.
	 *
	 * @var array
	 */
	private $_columns = array();

	/**
	 * Merge conflict regexps.
	 *
	 * @var array
	 */
	private $_mergeConflictRegExps = array();

	/**
	 * Log message limit.
	 *
	 * @var integer
	 */
	private $_logMessageLimit = 68;

	/**
	 * Current revision (e.g. in a working copy).
	 *
	 * @var integer|null
	 */
	private $_currentRevision;

	/**
	 * Creates instance of revision printer.
	 *
	 * @param DateHelper   $date_helper   Date helper.
	 * @param OutputHelper $output_helper Output helper.
	 */
	public function __construct(DateHelper $date_helper, OutputHelper $output_helper)
	{
		$this->_dateHelper = $date_helper;
		$this->_outputHelper = $output_helper;

		$this->_resetState();
	}

	/**
	 * Resets state.
	 *
	 * @return void
	 */
	private function _resetState()
	{
		$this->_columns = array();
		$this->_mergeConflictRegExps = array();
		$this->_logMessageLimit = 68;
	}

	/**
	 * Adds column to the output.
	 *
	 * @param integer $column Column.
	 *
	 * @return self
	 */
	public function withColumn($column)
	{
		$this->_columns[] = $column;

		return $this;
	}

	/**
	 * Sets merge conflict regexps.
	 *
	 * @param array $merge_conflict_regexps Merge conflict regexps.
	 *
	 * @return void
	 */
	public function setMergeConflictRegExps(array $merge_conflict_regexps)
	{
		$this->_mergeConflictRegExps = $merge_conflict_regexps;
	}

	/**
	 * Sets log message limit.
	 *
	 * @param integer $log_message_limit Log message limit.
	 *
	 * @return void
	 */
	public function setLogMessageLimit($log_message_limit)
	{
		$this->_logMessageLimit = $log_message_limit;
	}

	/**
	 * Sets current revision.
	 *
	 * @param integer $revision Revision.
	 *
	 * @return void
	 */
	public function setCurrentRevision($revision)
	{
		$this->_currentRevision = $revision;
	}

	/**
	 * Prints revisions.
	 *
	 * @param RevisionLog     $revision_log Revision log.
	 * @param array           $revisions    Revisions.
	 * @param OutputInterface $output       Output.
	 *
	 * @return void
	 */
	public function printRevisions(RevisionLog $revision_log, array $revisions, OutputInterface $output)
	{
		$table = new Table($output);
		$headers = array('Revision', 'Author', 'Date', 'Bug-ID', 'Log Message');

		$with_details = in_array(self::COLUMN_DETAILS, $this->_columns);

		// Add "Summary" header.
		$with_summary = in_array(self::COLUMN_SUMMARY, $this->_columns);

		if ( $with_summary ) {
			$headers[] = 'Summary';
		}

		// Add "Refs" header.
		$with_refs = in_array(self::COLUMN_REFS, $this->_columns);

		if ( $with_refs ) {
			$headers[] = 'Refs';
		}

		$with_merge_oracle = in_array(self::COLUMN_MERGE_ORACLE, $this->_columns);

		// Add "M.O." header.
		if ( $with_merge_oracle ) {
			$headers[] = 'M.O.';
		}

		// Add "Merged Via" header.
		$with_merge_status = in_array(self::COLUMN_MERGE_STATUS, $this->_columns);

		if ( $with_merge_status ) {
			$headers[] = 'Merged Via';
		}

		$table->setHeaders($headers);

		$prev_bugs = null;
		$last_color = 'yellow';
		$last_revision = end($revisions);

		$project_path = $revision_log->getProjectPath();

		$bugs_per_row = $with_details ? 1 : 3;

		$revisions_data = $revision_log->getRevisionsData('summary', $revisions);
		$revisions_paths = $revision_log->getRevisionsData('paths', $revisions);
		$revisions_bugs = $revision_log->getRevisionsData('bugs', $revisions);
		$revisions_refs = $revision_log->getRevisionsData('refs', $revisions);

		if ( $with_merge_status ) {
			$revisions_merged_via = $revision_log->getRevisionsData('merges', $revisions);
			$revisions_merged_via_refs = $revision_log->getRevisionsData(
				'refs',
				call_user_func_array('array_merge', $revisions_merged_via)
			);
		}

		foreach ( $revisions as $revision ) {
			$revision_data = $revisions_data[$revision];

			$new_bugs = $revisions_bugs[$revision];

			if ( isset($prev_bugs) && $new_bugs !== $prev_bugs ) {
				$last_color = $last_color === 'yellow' ? 'magenta' : 'yellow';
			}

			$row = array(
				$revision,
				$revision_data['author'],
				$this->_dateHelper->getAgoTime($revision_data['date']),
				$this->_outputHelper->formatArray($new_bugs, $bugs_per_row, $last_color),
				$this->_generateLogMessageColumn($with_details, $revision_data),
			);

			$revision_paths = $revisions_paths[$revision];

			// Add "Summary" column.
			if ( $with_summary ) {
				$row[] = $this->_generateSummaryColumn($revision_paths);
			}

			// Add "Refs" column.
			if ( $with_refs ) {
				$row[] = $this->_outputHelper->formatArray(
					$revisions_refs[$revision],
					1
				);
			}

			// Add "M.O." column.
			if ( $with_merge_oracle ) {
				$merge_conflict_prediction = $this->_getMergeConflictPrediction($revision_paths);
				$row[] = $merge_conflict_prediction ? '<error>' . count($merge_conflict_prediction) . '</error>' : '';
			}
			else {
				$merge_conflict_prediction = array();
			}

			// Add "Merged Via" column.
			if ( $with_merge_status ) {
				$row[] = $this->_generateMergedViaColumn($revisions_merged_via[$revision], $revisions_merged_via_refs);
			}

			if ( $revision === $this->_currentRevision ) {
				foreach ( $row as $index => $cell ) {
					$row[$index] = sprintf('<fg=white;options=bold>%s</>', $cell);
				}
			}

			$table->addRow($row);

			if ( $with_details ) {
				$details = $this->_generateDetailsRowContent(
					$revision,
					$revisions_refs,
					$revision_paths,
					$merge_conflict_prediction,
					$project_path
				);

				$table->addRow(new TableSeparator());
				$table->addRow(array(
					new TableCell($details, array('colspan' => count($headers))),
				));

				if ( $revision != $last_revision ) {
					$table->addRow(new TableSeparator());
				}
			}

			$prev_bugs = $new_bugs;
		}

		$table->render();

		$this->_resetState();
	}

	/**
	 * Returns log message.
	 *
	 * @param boolean $with_details  With details.
	 * @param array   $revision_data Revision data.
	 *
	 * @return string
	 */
	private function _generateLogMessageColumn($with_details, array $revision_data)
	{
		if ( $with_details ) {
			// When details requested don't transform commit message except for word wrapping.
			// FIXME: Not UTF-8 safe solution.
			$log_message = wordwrap($revision_data['msg'], $this->_logMessageLimit);

			return $log_message;
		}
		else {
			// When details not requested only operate on first line of commit message.
			list($log_message,) = explode(PHP_EOL, $revision_data['msg']);
			$log_message = preg_replace('/^\[fixes:.*?\]/s', "\xE2\x9C\x94", $log_message);

			if ( strpos($revision_data['msg'], PHP_EOL) !== false
				|| mb_strlen($log_message) > $this->_logMessageLimit
			) {
				$log_message = mb_substr($log_message, 0, $this->_logMessageLimit - 3) . '...';

				return $log_message;
			}

			return $log_message;
		}
	}

	/**
	 * Generates change summary for a revision.
	 *
	 * @param array $revision_paths Revision paths.
	 *
	 * @return string
	 */
	private function _generateSummaryColumn(array $revision_paths)
	{
		$summary = array('added' => 0, 'changed' => 0, 'removed' => 0);

		foreach ( $revision_paths as $path_data ) {
			$path_action = $path_data['action'];

			if ( $path_action === 'A' ) {
				$summary['added']++;
			}
			elseif ( $path_action === 'D' ) {
				$summary['removed']++;
			}
			else {
				$summary['changed']++;
			}
		}

		if ( $summary['added'] ) {
			$summary['added'] = '<fg=green>+' . $summary['added'] . '</>';
		}

		if ( $summary['removed'] ) {
			$summary['removed'] = '<fg=red>-' . $summary['removed'] . '</>';
		}

		return implode(' ', array_filter($summary));
	}

	/**
	 * Returns merge conflict path predictions.
	 *
	 * @param array $revision_paths Revision paths.
	 *
	 * @return array
	 */
	private function _getMergeConflictPrediction(array $revision_paths)
	{
		if ( !$this->_mergeConflictRegExps ) {
			return array();
		}

		$conflict_paths = array();

		foreach ( $revision_paths as $revision_path ) {
			foreach ( $this->_mergeConflictRegExps as $merge_conflict_regexp ) {
				if ( preg_match($merge_conflict_regexp, $revision_path['path']) ) {
					$conflict_paths[] = $revision_path['path'];
				}
			}
		}

		return $conflict_paths;
	}

	/**
	 * Generates content for "Merged Via" cell content.
	 *
	 * @param array $merged_via                Merged Via.
	 * @param array $revisions_merged_via_refs Merged Via Refs.
	 *
	 * @return string
	 */
	private function _generateMergedViaColumn(array $merged_via, array $revisions_merged_via_refs)
	{
		if ( !$merged_via ) {
			return '';
		}

		$merged_via_enhanced = array();

		foreach ( $merged_via as $merged_via_revision ) {
			$merged_via_revision_refs = $revisions_merged_via_refs[$merged_via_revision];

			if ( $merged_via_revision_refs ) {
				$merged_via_enhanced[] = $merged_via_revision . ' (' . implode(',', $merged_via_revision_refs) . ')';
			}
			else {
				$merged_via_enhanced[] = $merged_via_revision;
			}
		}

		return $this->_outputHelper->formatArray($merged_via_enhanced, 1);
	}

	/**
	 * Generates details row content.
	 *
	 * @param integer $revision                  Revision.
	 * @param array   $revisions_refs            Refs.
	 * @param array   $revision_paths            Revision paths.
	 * @param array   $merge_conflict_prediction Merge conflict prediction.
	 * @param string  $project_path              Project path.
	 *
	 * @return string
	 */
	private function _generateDetailsRowContent(
		$revision,
		array $revisions_refs,
		array $revision_paths,
		array $merge_conflict_prediction,
		$project_path
	) {
		$details = '<fg=white;options=bold>Changed Paths:</>';
		$path_cut_off_regexp = $this->getPathCutOffRegExp($project_path, $revisions_refs[$revision]);

		foreach ( $revision_paths as $path_data ) {
			$path_action = $path_data['action'];
			$relative_path = $this->_getRelativeLogPath($path_data, 'path', $path_cut_off_regexp);

			$details .= PHP_EOL . ' * ';

			if ( $path_action === 'A' ) {
				$color_format = 'fg=green';
			}
			elseif ( $path_action === 'D' ) {
				$color_format = 'fg=red';
			}
			else {
				$color_format = in_array($path_data['path'], $merge_conflict_prediction) ? 'error' : '';
			}

			$to_colorize = array($path_action . '    ' . $relative_path);

			if ( isset($path_data['copyfrom-path']) ) {
				// TODO: When copy happened from different ref/project, then relative path = absolute path.
				$copy_from_rev = $path_data['copyfrom-rev'];
				$copy_from_path = $this->_getRelativeLogPath($path_data, 'copyfrom-path', $path_cut_off_regexp);
				$to_colorize[] = '        (from ' . $copy_from_path . ':' . $copy_from_rev . ')';
			}

			if ( $color_format ) {
				$details .= '<' . $color_format . '>';
				$details .= implode('</>' . PHP_EOL . '<' . $color_format . '>', $to_colorize);
				$details .= '</>';
			}
			else {
				$details .= implode(PHP_EOL, $to_colorize);
			}
		}

		return $details;
	}

	/**
	 * Returns path cut off regexp.
	 *
	 * @param string $project_path Project path.
	 * @param array  $refs         Refs.
	 *
	 * @return string
	 */
	protected function getPathCutOffRegExp($project_path, array $refs)
	{
		$ret = array();

		// Remove ref from path only for single-ref revision.
		/*if ( count($refs) === 1 ) {
			$ret[] = $project_path . reset($refs) . '/';
		}*/

		// Always remove project path.
		$ret[] = $project_path;

		return '#^(' . implode('|', array_map('preg_quote', $ret)) . ')#';
	}

	/**
	 * Returns relative path to "svn log" returned path.
	 *
	 * @param array  $path_data           Path data.
	 * @param string $path_key            Path key.
	 * @param string $path_cut_off_regexp Path cut off regexp.
	 *
	 * @return string
	 */
	private function _getRelativeLogPath(array $path_data, $path_key, $path_cut_off_regexp)
	{
		$ret = preg_replace($path_cut_off_regexp, '', $path_data[$path_key], 1);

		if ( $ret === '' ) {
			$ret = '.';
		}

		return $ret;
	}

}
