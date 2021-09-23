<?php
/**
 * @package       Joomla.Administrator
 * @subpackage    com_scheduler
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

/** Implements the MVC Model for TaskView. */

namespace Joomla\Component\Scheduler\Administrator\Model;

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Component\Scheduler\Administrator\Helper\ExecRuleHelper;
use Joomla\Component\Scheduler\Administrator\Helper\SchedulerHelper;

/**
 * MVC Model to interact with the Scheduler DB.
 * Implements methods to add, remove, edit tasks.
 *
 * @since  __DEPLOY_VERSION__
 */
class TaskModel extends AdminModel
{
	/**
	 * Maps logical states to their values in the DB
	 * ? Do we end up using this?
	 *
	 * @var array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $STATES = [
		'enabled'  => 1,
		'disabled' => 0,
		'trashed'  => -2
	];

	/**
	 * Prefix used with controller messages
	 *
	 * @var string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $text_prefix = 'COM_SCHEDULER';

	/**
	 * Type alias for content type
	 *
	 * @var string
	 * @since  __DEPLOY_VERSION__
	 */
	public $typeAlias = 'com_scheduler.task';

	/**
	 * The Application object, for convenience
	 *
	 * @var AdministratorApplication $app
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;


	/**
	 * TaskModel constructor. Needed just to set $app
	 *
	 * @param   array                      $config       An array of configuration options
	 * @param   MVCFactoryInterface|null   $factory      The factory [?]
	 * @param   FormFactoryInterface|null  $formFactory  The form factory [?]
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, FormFactoryInterface $formFactory = null)
	{
		$config['events_map'] = $config['events_map'] ?? [];
		$config['events_map'] = array_merge(
			[
				'save'     => 'task',
				'validate' => 'task'
			],
			$config['events_map']
		);

		$this->app = Factory::getApplication();

		parent::__construct($config, $factory, $formFactory);
	}

	/**
	 * Fetches the form object associated with this model. By default,
	 * loads the corresponding data from the DB and binds it with the form.
	 *
	 * @param   array  $data      Data that needs to go into the form
	 * @param   bool   $loadData  Should the form load its data from the DB?
	 *
	 * @return Form|boolean  A JForm object on success, false on failure.
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function getForm($data = array(), $loadData = true)
	{
		Form::addFieldPath(JPATH_ADMINISTRATOR . 'components/com_scheduler/src/Field');

		/*
		 *  loadForm() (defined by FormBehaviourTrait) also loads the form data by calling
		 *  loadFormData() : $data [implemented here] and binds it to the form by calling
		 *  $form->bind($data).
		*/
		$form = $this->loadForm('com_scheduler.task', 'task', ['control' => 'jform', 'load_data' => $loadData]);

		if (empty($form))
		{
			return false;
		}

		$user = $this->app->getIdentity();

		// If new entry, set task type from state
		if ($this->getState('task.id', 0) === 0 && $this->getState('task.type') !== null)
		{
			$form->setValue('type', null, $this->getState('task.type'));
		}

		// @todo : Check if this is working as expected for new items (id == 0)
		if (!$user->authorise('core.edit.state', 'com_scheduler.task.' . $this->getState('task.id')))
		{
			// Disable fields
			$form->setFieldAttribute('state', 'disabled', 'true');

			// No "hacking" ._.
			$form->setFieldAttribute('state', 'filter', 'unset');
		}

		return $form;
	}

	/**
	 * Determine whether a record may be deleted taking into consideration
	 * the user's permissions over the record.
	 *
	 * @param   object  $record  The database row/record in question
	 *
	 * @return  boolean  True if the record may be deleted
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	protected function canDelete($record): bool
	{
		// Record doesn't exist, can't delete
		if (empty($record->id))
		{
			return false;
		}

		return $this->app->getIdentity()->authorise('core.delete', 'com_scheduler.task.' . $record->id);
	}

	/**
	 * Populate the model state, we use these instead of toying with input or the global state
	 *
	 * @return  void
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	protected function populateState(): void
	{
		$app = $this->app;

		$taskId   = $app->getInput()->getInt('id');
		$taskType = $app->getUserState('com_scheduler.add.task.task_type');

		// @todo: Remove this. Get the option through a helper call.
		$taskOption = $app->getUserState('com_scheduler.add.task.task_option');

		$this->setState('task.id', $taskId);
		$this->setState('task.type', $taskType);
		$this->setState('task.option', $taskOption);

		// Load component params, though com_scheduler does not (yet) have any params
		$cParams = ComponentHelper::getParams($this->option);
		$this->setState('params', $cParams);
	}

	/**
	 * Don't need to define this method since the parent getTable()
	 * implicitly deduces $name and $prefix anyways. This makes the object
	 * more transparent though.
	 *
	 * @param   string  $name     Name of the table
	 * @param   string  $prefix   Class prefix
	 * @param   array   $options  Model config array
	 *
	 * @return Table
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function getTable($name = 'Task', $prefix = 'Table', $options = array()): Table
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * Fetches the data to be injected into the form
	 *
	 * @return object  Associative array of form data.
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	protected function loadFormData()
	{
		$data = $this->app->getUserState('com_scheduler.edit.task.data', array());

		// If the data from UserState is empty, we fetch it with getItem()
		if (empty($data))
		{
			/** @var CMSObject $data */
			$data = $this->getItem();

			// @todo : further data processing goes here

			// For a fresh object, set exec-day and exec-time
			if (!($data->id ?? 0))
			{
				$data->execution_rules['exec-day']  = gmdate('d');
				$data->execution_rules['exec-time'] = gmdate('H:i');
			}
		}

		// Let plugins manipulate the data
		$this->preprocessData('com_scheduler.task', $data, 'task');

		return $data;
	}

	/**
	 * Overloads the parent getItem() method.
	 *
	 * @param   integer  $pk  Primary key
	 *
	 * @return  object|boolean  Object on success, false on failure
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		if (!is_object($item))
		{
			return false;
		}

		// Parent call leaves `execution_rules` and `cron_rules` JSON encoded
		$item->set('execution_rules', json_decode($item->get('execution_rules')));
		$item->set('cron_rules', json_decode($item->get('cron_rules')));

		$taskOption = SchedulerHelper::getTaskOptions()->findOption(
			($item->id ?? 0) ? ($item->type ?? 0) : $this->getState('task.type')
		);

		$item->set('taskOption', $taskOption);

		return $item;
	}

	/**
	 * @param   array  $data  The form data
	 *
	 * @return  boolean  True on success, false on failure
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function save($data): bool
	{
		// Clean up execution rules
		$data['execution_rules'] = $this->processExecutionRules($data['execution_rules']);

		$basisDayOfMonth = $data['execution_rules']['exec-day'];
		[$basisHour, $basisMinute] = explode(':', $data['execution_rules']['exec-time']);

		$data['last_execution'] = Factory::getDate('now', 'GMT')->format('Y-m')
			. "-$basisDayOfMonth $basisHour:$basisMinute:00";

		// Build the `cron_rules` column from `execution_rules`
		$data['cron_rules'] = $this->buildExecutionRules($data['execution_rules']);

		$data['next_execution'] = (new ExecRuleHelper($data))->nextExec();

		// If no params, we set as empty array.
		// ? Is this the right place to do this
		$data['params'] = $data['params'] ?? [];

		// Parent method takes care of saving to the table
		return parent::save($data);
	}

	/**
	 * Clean up and standardise execution rules
	 *
	 * @param   array  $unprocessedRules  The form data [? can just replace with execution_interval]
	 *
	 * @return array  Processed rules
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private function processExecutionRules(array $unprocessedRules): array
	{
		$executionRules = $unprocessedRules;

		$ruleType       = $executionRules['rule-type'];
		$retainKeys     = ['rule-type', $ruleType, 'exec-day', 'exec-time'];
		$executionRules = array_intersect_key($executionRules, array_flip($retainKeys));

		// Default to current date-time in UTC/GMT as the basis
		$executionRules['exec-day']  = $executionRules['exec-day'] ?: (string) gmdate('d');
		$executionRules['exec-time'] = $executionRules['exec-time'] ?: (string) gmdate('H:i');

		// If custom ruleset, sort it
		// ? Is this necessary
		if ($ruleType === 'custom')
		{
			foreach ($executionRules['custom'] as &$values)
			{
				sort($values);
			}
		}

		return $executionRules;
	}

	/**
	 * Private method to build execution expression from input execution rules.
	 * This expression is used internally to determine execution times/conditions.
	 *
	 * ! DRY violations here
	 *
	 * @param   array  $executionRules  Execution rules from the Task form, post-processing.
	 *
	 * @return array
	 *
	 * @throws \Exception
	 * @since  __DEPLOY_VERSION__
	 */
	private function buildExecutionRules(array $executionRules): array
	{
		// Maps interval strings, use with sprintf($map[intType], $interval)
		$intervalStringMap = [
			'minutes' => 'PT%dM',
			'hours'   => 'PT%dH',
			'days'    => 'P%dD',
			'months'  => 'P%dM',
			'years'   => 'P%dY'
		];

		$buildExecutionRules = [];

		$ruleType                      = $executionRules['rule-type'];
		$buildExecutionRules['visits'] = null;
		$buildExecutionRules['type']   = $execType = $executionRules['rule-type'] === 'custom' ? 'cron' : 'interval';

		if ($execType === 'interval')
		{
			// Rule type for intervals interval-<minute/hours/...>
			$subtype                    = explode('-', $ruleType)[1];
			$interval                   = $executionRules["interval-$subtype"];
			$buildExecutionRules['exp'] = sprintf($intervalStringMap[$subtype], $interval);
		}

		if ($execType === 'cron')
		{
			// ! custom matches are disabled in the form
			$customRules                = $executionRules['custom'];
			$buildExecutionRules['exp'] .= $this->wildcardIfMatch($customRules['minutes'], range(0, 59), true);
			$buildExecutionRules['exp'] .= ' ' . $this->wildcardIfMatch($customRules['hours'], range(0, 23), true);
			$buildExecutionRules['exp'] .= ' ' . $this->wildcardIfMatch($customRules['days_month'], range(1, 31), true);
			$buildExecutionRules['exp'] .= ' ' . $this->wildcardIfMatch($customRules['months'], range(1, 12), true);
			$buildExecutionRules['exp'] .= ' ' . $this->wildcardIfMatch($customRules['days_week'], range(0, 6), true);
		}

		return $buildExecutionRules;
	}

	/**
	 * Determine if an array is populated by all its possible values by comparison to a reference array, if found a
	 * match a wildcard '*' is returned.
	 *
	 * @param   array  $target       The target array
	 * @param   array  $reference    The reference array, populated by the complete set of possible values in $target
	 * @param   bool   $targetToInt  If true, converts $target array values to integers before comparing
	 *
	 * @return string  A wildcard string if $target is fully populated, else $target itself.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private function wildcardIfMatch(array $target, array $reference, bool $targetToInt = false): string
	{
		if ($targetToInt)
		{
			$target = array_map(
				static function (string $x): int {
					return (int) $x;
				},
				$target
			);
		}

		$isMatch = array_diff($reference, $target) === [];

		return $isMatch ? "*" : implode(',', $target);
	}

	/**
	 * Method to allow derived classes to preprocess the form.
	 *
	 * @param   Form    $form   A Form object.
	 * @param   mixed   $data   The data expected for the form.
	 * @param   string  $group  The name of the plugin group to import (defaults to "content").
	 *
	 * @return  void
	 *
	 * @throws  \Exception if there is an error in the form event.
	 * @since   __DEPLOY_VERSION__
	 */
	protected function preprocessForm(Form $form, $data, $group = 'content'): void
	{
		// Load the 'task' plugin group
		PluginHelper::importPlugin('task');

		// Let the parent method take over
		parent::preprocessForm($form, $data, $group);
	}
}
