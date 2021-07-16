<?php
/**
 * The SelectView default layout template.
 *
 * @package       Joomla.Administrator
 * @subpackage    com_cronjobs
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GPL v3
 * @codingStandardsIgnoreStart
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Cronjobs\Administrator\View\Select\HtmlView;

/** @var HtmlView $this */

$app = $this->app;

// ? : What does this do?
// ! : This is going down into loading the select-modal script, what does that do?
// $function  = $app->getInput()->get('function');

// TODO : Cronjob search script
$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_cronjobs.admin-plg-job-css');
$wa->useScript('com_cronjobs.admin-plg-job-search');

/*
 * if ($function) :
 * $wa->useScript('COM_CRONJOBS.admin-select-modal');
 * endif;*/
?>

<!-- Search box on below the toolbar -->
<div class="d-none" id="comCronjobsSelectSearchContainer">
	<div class="d-flex mt-2">
		<div class="ms-auto me-auto">
			<label class="visually-hidden" for="comCronjobsSelectSearch">
				<?php echo Text::_('COM_CRONJOBS_TYPE_CHOOSE'); ?>
			</label>
			<div class="input-group mb-3 me-sm-2">
				<input type="text" value=""
					   class="form-control" id="comCronjobsSelectSearch"
					   placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
				>
				<div class="input-group-text">
					<span class="icon-search" aria-hidden="true"></span>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Search box and related elements end -->

<div id="new-cronjobs-list">
	<div class="new-cronjob">
		<!-- More appropriate classes here -->
		<div class="jobs-alert alert alert-info d-none">
			<span class="icon-info-circle" aria-hidden="true"></span><span
					class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
			<?php echo Text::_('COM_CRONJOBS_MSG_MANAGE_NO_JOB_PLUGINS'); ?>
		</div>
		<h2 class="pb-3 ms-3" id="comCronjobsSelectTypeHeader">
			<?php echo Text::_('COM_CRONJOBS_TYPE_CHOOSE'); ?>
		</h2>
	</div>
	
	<!-- Parent card -->
	<div class="main-card card-columns p-4" id="comCronjobsSelectResultsContainer">
		<a href="" class="new-job mb-3 comCronjobsSelectCard">

		</a>

		<!-- Plugin job cards start below -->
		<?php foreach ($this->items as &$item) : ?>
			<?php // Prepare variables for the link. ?>
			<?php $link = 'index.php?option=com_cronjobs&task=cronjob.add&plg=' . $item->id . '&type=plugin'; ?>
			<?php $name = $this->escape($item->title); ?>
			<?php $desc = HTMLHelper::_('string.truncate', $this->escape(strip_tags($item->desc)), 200); ?>
			<!-- The job card begins -->
			<a href="<?php echo Route::_($link); ?>" class="new-job mb-3 comCronjobsSelectCard"
			   data-function="' . $this->escape($function) : ''; ?>"
			   aria-label="<?php echo Text::sprintf('COM_CRONJOBS_SELECT_PLG_JOB', $name); ?>">
				<div class="new-job-details">
					<h3 class="new-job-title"><?php echo $name; ?></h3>
					<p class="card-body new-job-caption p-0">
						<?php echo $desc; ?>
					</p>
				</div>
				<span class="new-job-link">
						<span class="icon-plus" aria-hidden="true"></span>
					</span>
			</a>
			<!-- The job card ends here -->
		<?php endforeach; ?>
	</div>
</div>
</div>
