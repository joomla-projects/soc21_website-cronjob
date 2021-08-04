<?php
/**
 * Declares the CronjobsModel MVC Model.
 *
 * @package       Joomla.Administrator
 * @subpackage    com_cronjobs
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GPL v3
 */

namespace Joomla\Component\Cronjobs\Administrator\Model;

// Restrict direct access
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Cronjobs\Administrator\Helper\CronjobsHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;
use function defined;
use function in_array;
use function stripos;
use function substr;

/**
 * MVC Model to deal with operations concerning multiple 'Cronjob' entries.
 *
 * @since __DEPLOY_VERSION__
 */
class CronjobsModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array                     $config   An optional associative array of configuration settings.
	 *
	 * @param   MVCFactoryInterface|null  $factory  The factory.
	 *
	 * @throws Exception
	 * @since  __DEPLOY_VERSION__
	 * @see    \JControllerLegacy
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null)
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'a.id',
				'asset_id', 'a.asset_id',
				'title', 'a.title',
				'type', 'a.type',
				'type_title', 'j.type_title',
				'trigger', 'a.trigger',
				'state', 'a.state',
				'last_exit_code', 'a.last_exit_code',
				'last_execution', 'a.last_execution',
				'next_execution', 'a.next_execution',
				'times_executed', 'a.times_executed',
				'times_failed', 'a.times_failed',
				'ordering', 'a.ordering',
				'note', 'a.note',
				'created', 'a.created',
				'created_by', 'a.created_by'
			];
		}

		parent::__construct($config, $factory);
	}

	/**
	 * Method to get an array of data items.
	 *
	 * @return array|boolean  An array of data items on success, false on failure.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function getItems()
	{
		return parent::getItems();
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * ? What does this do internally ?
	 * TODO:
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return string  A store id.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function getStoreId($id = ''): string
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');
		$id .= ':' . $this->getState('filter.job_type');

		// ? Does the store id need more specificity

		return parent::getStoreId($id);
	}

	/**
	 * Method to create a query for a list of items.
	 * ! No filters at the moment ⚠
	 *
	 * @return QueryInterface
	 *
	 * @throws Exception
	 * @since  __DEPLOY_VERSION__
	 */
	protected function getListQuery(): QueryInterface
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$user = Factory::getApplication()->getIdentity();

		/*
		 * Select the required fields from the table.
		 * ? Do we need all these defaults ?
		 */
		$query->select(
			$this->getState(
				'list.select',
				'a.id, a.asset_id, a.title, a.type, a.trigger, a.execution_rules, a.state, a.last_exit_code' .
				', a.last_execution, a.next_execution, a.times_executed, a.times_failed, a.ordering, a.note'
			)
			// ? Does 'list.select' exist ?
		);

		// From the #__cronjobs table as 'a'
		$query->from($db->quoteName('#__cronjobs', 'a'));

		// Filter over state ----
		$state = $this->getState('filter.state');

		if (is_numeric($state))
		{
			$state = (int) $state;
			$query->where($db->quoteName('a.state') . '= :state')
				->bind(':state', $state);
		}

		// Filter over exit code ----
		$exitCode = $this->getState('filter.last_exit_code');

		if (is_numeric($exitCode))
		{
			$exitCode = (int) $exitCode;
			$query->where($db->quoteName('a.last_exit_code') . '= :last_exit_code')
				->bind(':last_exit_code', $exitCode, ParameterType::INTEGER);
		}

		// Filter over search string if set (title, type title, note, id) ----
		$searchStr = $this->getState('filter.search');

		if (!empty($searchStr))
		{
			// Allow search by ID
			if (stripos($searchStr, 'id:') === 0)
			{
				// Add array support [?]
				$id = (int) substr($searchStr, 3);
				$query->where($db->quoteName('a.id') . '= :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			// Search by type is handled exceptionally in _getList() [TODO]
			elseif (stripos($searchStr, 'type:') !== 0)
			{
				// The where clause is extended to allow other filters to act
				$query->extendWhere(
					'AND',
					[
						$db->quoteName('a.title') . ' LIKE :searchStr',
						$db->quoteName('a.note') . 'LIKE :searchStr'
					],
					'OR'
				)
					->bind(':searchStr', $searchStr, ParameterType::STRING);
			}
		}

		// Add list ordering clause. ----
		$orderCol = $this->state->get('list.ordering', 'a.title');
		$orderDir = $this->state->get('list.direction', 'desc');

		// Type title ordering is handled exceptionally in _getList()
		if ($orderCol !== 'j.type_title')
		{
			// If ordering by type or state, also order by title.
			if (in_array($orderCol, ['a.type', 'a.state']))
			{
				// TODO : Test if things are working as expected
				$query->order($db->quoteName('a.title') . ' ' . $orderDir);
			}

			$query->order($db->quoteName($orderCol) . ' ' . $orderDir);
		}

		return $query;
	}

	/**
	 * Overloads the parent _getList() method.
	 * Takes care of attaching CronOption objects and sorting by type titles.
	 *
	 * @param   DatabaseQuery  $query       The database query to get the list with
	 * @param   int            $limitstart  The list offset
	 * @param   int            $limit       Number of list items to fetch
	 *
	 * @return object[]
	 *
	 * @throws Exception
	 * @since __DEPLOY_VERSION__
	 * @codingStandardsIgnoreStart
	 */
	protected function _getList($query, $limitstart = 0, $limit = 0): array
	{
		/** @codingStandardsIgnoreEnd */

		// Get stuff from the model state
		$listOrder = $this->getState('list.ordering', 'a.title');
		$listDirectionN = strtolower($this->getState('list.direction', 'desc')) == 'desc' ? -1 : 1;

		// Set limit parameters and get object list
		$query->setLimit($limit, $limitstart);
		$this->getDbo()->setQuery($query);
		$responseList = $this->getDbo()->loadObjectList();

		// Attach CronOptions objects and a safe type title
		$this->attachCronOptions($responseList);

		// If ordering by non-db fields, we need to sort here in code
		if ($listOrder == 'j.type_title')
		{
			$responseList = ArrayHelper::sortObjects($responseList, 'safeTypeTitle', $listDirectionN, true, false);
		}

		// TODO: Implement filtering by type title here [filter form + search]

		// Filter out orphaned jobs if the state allows
		// ! This breaks pagination at the moment [TODO: fix]
		$showOrphaned = $this->getState('filter.show_orphaned');

		if (!$showOrphaned)
		{
			$responseList = array_values(
				array_filter(
					$responseList,
					function (object $c) {
						return isset($c->cronOption);
					}
				)
			);
		}

		return $responseList;
	}

	/**
	 * For an array of items, attaches CronOption objects and (safe) type titles to each.
	 *
	 * @param   array  $items  Array of items, passed by reference
	 *
	 * @return void
	 *
	 * @throws Exception
	 * @since __DEPLOY_VERSION__
	 */
	private function attachCronOptions(array &$items): void
	{
		$cronOptions = CronjobsHelper::getCronOptions();

		foreach ($items as &$item)
		{
			$item->cronOption = $cronOptions->findOption($item->type);
			$item->safeTypeTitle = $item->cronOption->title ?? Text::_('COM_CRONJOBS_NA');
		}
	}

	/**
	 * Proxy for the parent method.
	 * Sets ordering defaults.
	 *
	 * @param   ?string  $ordering   Field to order/sort list by
	 * @param   ?string  $direction  Direction in which to sort list
	 *
	 * @return void
	 * @since __DEPLOY_VERSION__
	 */
	protected function populateState($ordering = 'a.id', $direction = 'ASC'): void
	{
		// Call the parent method
		parent::populateState($ordering, $direction);
	}

}
