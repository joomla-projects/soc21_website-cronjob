<?php
/**
 * Joomla! Content Management System.
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Console;

// Restrict direct access
defined('JPATH_PLATFORM') or die;

use Assert\AssertionFailedException;
use Joomla\Component\Scheduler\Administrator\Scheduler\Scheduler;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Console\Application;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to run scheduled tasks.
 *
 * @since __DEPLOY_VERSION__
 */
class TasksRunCommand extends AbstractCommand
{
	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'scheduler:run';

	/**
	 * The console application
	 *
	 * @var Application
	 * @since __DEPLOY__VERSION__
	 */
	protected $application;

	/**
	 * @var SymfonyStyle
	 * @since  __DEPLOY_VERSION__
	 */
	private $ioStyle;

	/**
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return integer The command exit code.
	 *
	 * @throws AssertionFailedException
	 * @since __DEPLOY_VERSION__
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		/**
		 * Not as a class constant because of some the autoload order doesn't let us
		 * load the namespace when it's time to do that (why?)
		 */
		static $outTextMap = [
		Status::OK         => 'Task#%1$02d \'%2$s\' processed in %3$.2f seconds.',
		Status::NO_LOCK    => '<comment>Task#%1$02d \'%2$s\' already running.</comment>',
		Status::NO_ROUTINE => '<error>Task#%1$02d \'%2$s\' is orphaned! Visit the backend to resolve.</error>',
		'N/A' => '<error>Task#%1$02d \'%2$s\' exited with code %4$d in %3$.2f seconds.</error>'
		];

		$this->configureIo($input, $output);
		$this->ioStyle->title('Run tasks');

		$scheduler = new Scheduler;

		$id = $input->getOption('id');
		$title = $input->getOption('title');
		$all = $input->getOption('all');

		if ($id)
		{
			$records[] = $scheduler->fetchTaskRecord($id);
		}
		elseif ($title)
		{
			$records[] = $scheduler->fetchTaskRecord(0, $title);
		}
		else
		{
			$filters = $scheduler::TASK_QUEUE_FILTERS;
			$listConfig = $scheduler::TASK_QUEUE_LIST_CONFIG;
			$listConfig['limit'] = ($all ? null : 1);

			$records = $scheduler->fetchTaskRecords($filters, $listConfig);
		}

		if (($id || $title) && !$records[0])
		{
			$this->ioStyle->writeln('<error>No matching task found!</error>');

			return Status::NO_TASK;
		}
		elseif (!$records)
		{
			$this->ioStyle->writeln('<error>No tasks due!</error>');

			return Status::NO_TASK;
		}

		@set_time_limit(0);
		$status = ['startTime' => microtime(true)];
		$taskCount = count($records);
		$exit = Status::OK;

		foreach ($records as $record)
		{
			$cStart = microtime(true);
			$exit = $scheduler->runTask($record->id);
			$duration = microtime(true) - $cStart;
			$key = (array_key_exists($exit, $outTextMap)) ? $exit : 'N/A';
			$this->ioStyle->writeln(sprintf($outTextMap[$key], $record->id, $record->title, $duration, $exit));
		}

		$netTime = round(microtime(true) - $status['startTime'], 2);
		$this->ioStyle->newLine();
		$this->ioStyle->writeln("<info>Finished running $taskCount tasks in $netTime seconds.</info>");

		return $taskCount === 1 ? $exit : Status::OK;
	}

	/**
	 * Configure the IO.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private function configureIO(InputInterface $input, OutputInterface $output)
	{
		$this->ioStyle = new SymfonyStyle($input, $output);
	}

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function configure(): void
	{
		$this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'The id of the task to run');
		$this->addOption('title', 't', InputOption::VALUE_REQUIRED, 'The title of the task to run, can be incomplete.');
		$this->addOption('all', '', InputOption::VALUE_NONE, 'Run all due tasks. Note that this is overridden if --id or --title are used.');

		$help = "<info>%command.name%</info> run scheduled tasks.
		\nUsage: <info>php %command.full_name% [flags]</info>";

		$this->setDescription('List all scheduled tasks');
		$this->setHelp($help);
	}
}