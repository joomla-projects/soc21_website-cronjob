<?php
/**
 * Declares a list field with all possible states for a (cron)job entry.
 * @package         Joomla.Administrator
 * @subpackage      com_cronjobs
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Cronjobs\Administrator\Field;

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\PredefinedlistField;

/**
 * A predefined list field with all possible states for a com_cronjobs entry.
 *
 * @since __DEPLOY_VERSION__
 */
class CronjobStateField extends PredefinedlistField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.2
	 */
	public $type = 'CronjobState';

	/**
	 * Available states
	 *
	 * @var  array
	 * @since  3.2
	 */
	protected $predefinedOptions = [
		-2 => 'JTRASHED',
		0 => 'JDISABLED',
		1 => 'JENABLED',
		'*' => 'JALL'
	];
}
