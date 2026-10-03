<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_eventbooking
 * @author     Chris Vaughan  <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2025 Ruby Tuesday
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Session\Session;
use \Joomla\CMS\User\UserFactoryInterface;

$user = Factory::getApplication()->getIdentity();
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canCreate = $user->authorise('core.create', 'com_ra_eventbooking') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'eventsettingform.xml');
$canEdit = $user->authorise('core.edit', 'com_ra_eventbooking') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'eventsettingform.xml');
$canCheckin = $user->authorise('core.manage', 'com_ra_eventbooking');
$canChange = $user->authorise('core.edit.state', 'com_ra_eventbooking');
$canDelete = $user->authorise('core.delete', 'com_ra_eventbooking');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_ra_eventbooking.list');
?>

<?php if ($this->params->get('show_page_heading')) : ?>
    <div class="page-header">
        <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
    </div>
<?php endif; ?>
<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">
          <?php
          if (!empty($this->filterForm)) {
              echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
          }
          ?>
    <div class="table-responsive">
        <table class="table table-striped" id="eventsettingList">
            <thead>
                <tr>
                    <th >
                        <?php echo HTMLHelper::_('grid.sort', 'JPUBLISHED', 'a.state', $listDirn, $listOrder); ?>
                    </th>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_RA_EVENTBOOKING_EVENTSETTINGS_EVENT_ID', 'a.event_id', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'EVENT', 'a.event_data', $listDirn, $listOrder); ?>
                    </th>

                    <?php if ($canEdit || $canDelete): ?>
                        <th class="center">
                            <?php echo Text::_('COM_RA_EVENTBOOKING_EVENTSETTINGS_ACTIONS'); ?>
                        </th>
                    <?php endif; ?>

                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                        <div class="pagination">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    </td>
                </tr>
            </tfoot>
            <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php $canEdit = $user->authorise('core.edit', 'com_ra_eventbooking'); ?>
                    <?php if (!$canEdit && $user->authorise('core.edit.own', 'com_ra_eventbooking')): ?>
                        <?php $canEdit = Factory::getApplication()->getIdentity()->id == $item->created_by; ?>
                    <?php endif; ?>

                    <tr class="row<?php echo $i % 2; ?>">

                        <td>
                            <?php $class = ($canChange) ? 'active' : 'disabled'; ?>
                            <a class="btn btn-micro <?php echo $class; ?>" href="<?php echo ($canChange) ? Route::_('index.php?option=com_ra_eventbooking&task=eventsetting.publish&id=' . $item->id . '&state=' . (($item->state + 1) % 2), false, 2) : '#'; ?>">
                                <?php if ($item->state == 1): ?>
                                    <i class="icon-publish"></i>
                                <?php else: ?>
                                    <i class="icon-unpublish"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $canCheckin = Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_ra_eventbooking.' . $item->id) || $item->checked_out == Factory::getApplication()->getIdentity()->id;

                            if ($canCheckin && $item->checked_out > 0) {
                                echo ' <a href="' . Route::_('index.php?option=com_ra_eventbooking&task=eventsetting.checkin&id=' . $item->id . '&' . Session::getFormToken() . '=1') . '">';
                                echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'eventsetting.', false) . '</a>';
                            }
                            if ($canEdit) {
                                echo '<a href="' . Route::_('index.php?option=com_ra_eventbooking&task=eventsetting.edit&id=' . (int) $item->id) . '" title="Edit record">';
                                //  echo '<a href="' . Route::_('index.php?option=com_ra_eventbooking&view=eventsetting&id=' . (int) $item->id . '">');
                                echo $this->escape($item->event_id) . '</a>';
                            } else {
                                echo $this->escape($item->event_id);
                            }
                            ?>
                        </td>

                        <?php
                        $event = $item->event_data;
                        if ($event === "" OR $event === null) {
                            echo '<td>Not set</td>';
                        } else {
                            $data = \json_decode($event);
                            $out = $data->date;
                            $out .= '<br>' . $data->title;
                            echo '<td>' . $out . '</td>';
                        }
                        ?>


                        <?php if ($canEdit || $canDelete): ?>
                            <td class="center">
                                <?php $canCheckin = Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_ra_eventbooking.' . $item->id) || $item->checked_out == Factory::getApplication()->getIdentity()->id; ?>

                                <?php if ($canEdit && $item->checked_out == 0): ?>
                                    <a href="<?php echo Route::_('index.php?option=com_ra_eventbooking&task=eventsetting.edit&id=' . $item->id, false, 2); ?>" class="btn btn-mini" type="button" title="Edit record"><i class="icon-edit" ></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <a href="<?php echo Route::_('index.php?option=com_ra_eventbooking&task=eventsettingform.remove&id=' . $item->id, false, 2); ?>" class="btn btn-mini delete-button" type="button" title="Delete record"><i class="icon-trash" ></i></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($canCreate) : ?>
        <a href="<?php echo Route::_('index.php?option=com_ra_eventbooking&task=eventsettingform.edit&id=0', false, 0); ?>"
           class="btn btn-success btn-small link-button mintcake"><i
                class="icon-plus"></i>
            <?php echo Text::_('COM_RA_EVENTBOOKING_ADD_ITEM'); ?></a>
    <?php endif; ?>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value=""/>
    <input type="hidden" name="filter_order_Dir" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
if ($canDelete) {
    $wa->addInlineScript("
			jQuery(document).ready(function () {
				jQuery('.delete-button').click(deleteItem);
			});

			function deleteItem() {

				if (!confirm(\"" . Text::_('COM_RA_EVENTBOOKING_DELETE_MESSAGE') . "\")) {
					return false;
				}
			}
		", [], [], ["jquery"]);
}
?>