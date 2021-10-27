<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.ScheduleRunner
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Restrict direct access
defined('_JEXEC') or die;

use Assert\AssertionFailedException;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Extension;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Scheduler\Administrator\Scheduler\Scheduler;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * This plugin implements listeners to support a visitor-triggered lazy-scheduling pattern.
 * If `com_scheduler` is installed/enabled and its configuration allows unprotected lazy scheduling, this plugin
 * injects into each response with an HTML context a JS file {@see PlgSystemSchedulerunner::injectScheduleRunner()} that
 * sets up an AJAX callback to trigger the scheduler {@see PlgSystemSchedulerunner::runScheduler()}. This is achieved
 * through a call to the `com_ajax` component.
 * Also supports the scheduler component configuration form through auto-generation of the webcron key and injection
 * of JS of usability enhancement.
 *
 * @since __DEPLOY_VERSION__
 */
class PlgSystemSchedulerunner extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Length of auto-generated webcron key.
	 *
	 * @var integer
	 * @since __DEPLOY_VERSION__
	 */
	private const WEBCRON_KEY_LENGTH = 20;

	/**
	 * @var  CMSApplication
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since __DEPLOY_VERSION__
	 *
	 * @throws Exception
	 */
	public static function getSubscribedEvents(): array
	{
		$config = ComponentHelper::getParams('com_scheduler');
		$app = Factory::getApplication();

		$mapping  = [];

		if ($app->isClient('site') || $app->isClient('administrator'))
		{
			$mapping['onBeforeCompileHead'] = 'injectLazyJS';
			$mapping['onAjaxRunSchedulerLazy'] = 'runLazyCron';

			// Only allowed in the frontend
			if ($app->isClient('site'))
			{
				if ($config->get('webcron.enabled'))
				{
					$mapping['onAjaxRunSchedulerWebcron'] = 'runWebCron';
				}
			}
			elseif ($app->isClient('administrator'))
			{
				$mapping['onContentPrepareForm'] = 'enhanceSchedulerConfig';
				$mapping['onExtensionBeforeSave'] = 'generateWebcronKey';

				$mapping['onAjaxRunSchedulerTest'] = 'runTestCron';
			}
		}

		return $mapping;
	}

	/**
	 * Inject JavaScript to trigger the scheduler in HTML contexts.
	 *
	 * @param   Event  $event  The onBeforeCompileHead event.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function injectLazyJS(Event $event): void
	{
		// Only inject in HTML documents
		if ($this->app->getDocument()->getType() !== 'html')
		{
			return;
		}

		$config = ComponentHelper::getParams('com_scheduler');

		if (!$config->get('lazy_scheduler.enabled'))
		{
			return;
		}

		// Add configuration options
		$triggerInterval = $config->get('lazy_scheduler.interval', 300);
		$this->app->getDocument()->addScriptOptions('plg_system_schedulerunner', ['interval' => $triggerInterval]);

		// Load and injection directive
		$wa = $this->app->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('plg_system_schedulerunner');
		$wa->useScript('plg_system_schedulerunner.run-schedule');
	}

	/**
	 * Runs the lazy cron in the frontend when activated. No ID allowed
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 *
	 * @throws AssertionFailedException
	 *
	 * @throws Exception
	 */
	public function runLazyCron()
	{
		$config = ComponentHelper::getParams('com_scheduler');

		if (!$config->get('lazy_scheduler.enabled'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Since `navigator.sendBeacon()` may time out, allow execution after disconnect if possible.
		if (\function_exists('ignore_user_abort'))
		{
			ignore_user_abort(true);
		}

		$this->runScheduler();
	}

	/**
	 * Runs the webcron and uses an ID if given.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 *
	 * @throws AssertionFailedException
	 * @throws Exception
	 */
	public function runWebCron()
	{
		$config = ComponentHelper::getParams('com_scheduler');

		$hash = $config->get('webcron.key');

		if (!strlen($hash) || $hash !== $this->app->input->get('hash'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$id = (int) $this->app->input->getInt('id');

		$this->runScheduler($id);
	}

	/**
	 * Runs the test cron in the backend. ID is required
	 *
	 * @param   Event  $event  The onAjaxRunScheduler event.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 *
	 * @throws AssertionFailedException
	 * @throws Exception
	 */
	public function runTestCron(Event $event)
	{
		$id = (int) $this->app->input->getInt('id');

		$user = Factory::getUser();

		if (empty($id) || !$user->authorise('core.testrun', 'com_scheduler.task.' . $id))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$task = $this->runScheduler($id, true);

		if ($task)
		{
			$event->addArgument('result', $task->getContent());
		}
	}

	/**
	 * Run the scheduler, allowing execution of a single due task.
	 *
	 * @param   integer  $id           The optional ID of the task to run
	 * @param   boolean  $unpublished  Allow execution of unpublished tasks?
	 *
	 * @return Task|boolean
	 *
	 * @since __DEPLOY_VERSION__
	 * @throws AssertionFailedException
	 *
	 */
	protected function runScheduler(int $id = 0, bool $unpublished = false): ?Task
	{
		return (new Scheduler)->runTask($id, $unpublished);
	}

	/**
	 * Enhance the scheduler config form by dynamically populating or removing display fields.
	 * @todo Move to another plugin?
	 *
	 * @param   EventInterface  $event  The onContentPrepareForm event.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function enhanceSchedulerConfig(EventInterface $event): void
	{
		/** @var Form $form */
		$form = $event->getArgument('0');
		$data = $event->getArgument('1');

		if ($form->getName() !== 'com_config.component'
			|| $this->app->input->get('component') !== 'com_scheduler')
		{
			return;
		}

		if (!empty($data['webcron']['key']))
		{
			$form->removeField('generate_key_on_save', 'webcron');

			$relative = 'index.php?option=com_ajax&plugin=RunSchedulerLazy&group=system&format=json&hash=' . $data['webcron']['key'];
			$link = Route::link('site', $relative, false, Route::TLS_IGNORE, true);
			$form->setValue('base_link', 'webcron', $link);
		}
		else
		{
			$form->removeField('base_link', 'webcron');
			$form->removeField('reset_key', 'webcron');
		}
	}

	/**
	 * Auto-generate a key/hash for the webcron functionality.
	 * This method acts on table save, when a hash doesn't already exist or a reset is required.
	 * @todo Move to another plugin?
	 *
	 * @param   EventInterface  $event The onExtensionBeforeSave event.
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function generateWebcronKey(EventInterface $event): void
	{
		/** @var Extension $table */
		[$context, $table] = $event->getArguments();

		if ($context !== 'com_config.component'
			|| $this->app->input->get('component') !== 'com_scheduler')
		{
			return;
		}

		$params = new Registry(json_decode($table->params));

		if (empty($params->get('webcron.key'))
			|| (int) $params->get('webcron.reset_key') === 1)
		{
			$params->set('webcron.key', UserHelper::genRandomPassword(self::WEBCRON_KEY_LENGTH));
		}

		$params->remove('webcron.base_link');
		$params->remove('webcron.reset_key');
		$table->params = $params->toString();
	}
}
