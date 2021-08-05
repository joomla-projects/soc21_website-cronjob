<?php
/**
 * Declares the validation class for execution-rules.
 *
 * @package       Joomla.Administrator
 * @subpackage    com_cronjobs
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GPL v3
 */

namespace Joomla\Component\Cronjobs\Administrator\Rule;

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Form\Rule\OptionsRule;
use Joomla\Registry\Registry;
use SimpleXMLElement;

/**
 * The ExecutionRulesRule Class.
 * Validates execution rules, with input for other fields as context.
 *
 * @since __DEPLOY_VERSION__
 */
class ExecutionRulesRule extends FormRule
{
	/**
	 * @var string  RULE_TYPE_FIELD   The field containing the rule type to test against
	 * @since __DEPLOY_VERSION__
	 */
	private const RULE_TYPE_FIELD = "execution_rules.rule-type";

	/**
	 * @var string CUSTOM_RULE_GROUP  The field group containing custom execution rules
	 * @since __DEPLOY_VERSION__
	 */
	private const CUSTOM_RULE_GROUP = "execution_rules.custom";

	/**
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   ?string           $group    The field name group control value. This acts as an array container for the field.
	 *                                      For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                      full field name would end up being "bar[foo]".
	 * @param   ?Registry         $input    An optional Registry object with the entire data set to validate against the entire form.
	 * @param   ?Form             $form     The form object for which the field is being tested.
	 *
	 * @return boolean
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function test(SimpleXMLElement $element, $value, $group = null, Registry $input = null, Form $form = null): bool
	{
		$fieldName = (string) $element['name'];
		$ruleType = $input->get(self::RULE_TYPE_FIELD);

		if ($ruleType === $fieldName || ($ruleType === 'custom' && $group === self::CUSTOM_RULE_GROUP))
		{
			return self::validateField($element, $value, $group, $form);
		}

		return true;
	}

	/**
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement for the field.
	 * @param   mixed             $value    The field value.
	 * @param   ?string           $group    The form field group the element belongs to.
	 * @param   Form|null         $form     The Form object against which the field is tested/
	 *
	 * @return boolean  True if field is valid
	 *
	 * @since __DEPLOY_VERSION__
	 */
	private function validateField(SimpleXMLElement $element, $value, ?string $group = null, ?Form $form = null): bool
	{
		$elementType = (string) $element['type'];
		$optionsTest = true;

		// Test that the option is valid
		if ($elementType === 'cron')
		{
			$optionsTest = (new OptionsRule)->test($element, $value, $group, null, $form);
		}

		// ? Does the numeric IntervalField need validation

		return $value && $optionsTest;
	}
}
