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

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
//use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_eventbooking', JPATH_SITE);

$user = Factory::getApplication()->getIdentity();
$canEdit = Ra_eventbookingHelper::canUserEdit($this->item, $user);
?>

<div class="eventsetting-edit front-end-edit" >

    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
        </div>
    <?php endif; ?>
    <?php if (!$canEdit) : ?>
        <h3>
            <?php throw new \Exception(Text::_('COM_RA_EVENTBOOKING_ERROR_MESSAGE_NOT_AUTHORISED'), 403); ?>
        </h3>
    <?php else : ?>
        <?php if (!empty($this->item->id)): ?>
            <h1><?php // echo Text::sprintf('COM_RA_EVENTBOOKING_EDIT_ITEM_TITLE', $this->item->id);                     ?></h1>
        <?php else: ?>
            <h1><?php echo Text::_('COM_RA_EVENTBOOKING_ADD_ITEM_TITLE'); ?></h1>
        <?php endif; ?>

        <form id="form-eventsetting"
              action="<?php echo Route::_('index.php?option=com_ra_eventbooking&task=eventsettingform.save'); ?>"
              method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

            <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

            <input type="hidden" name="jform[state]" value="<?php echo isset($this->item->state) ? $this->item->state : ''; ?>" />
            <div class="main-card">

                <?php
                echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'basic'));

                echo HTMLHelper::_('uitab.addTab', 'myTab', 'basic', Text::_('Basic Info'));

                echo $this->form->renderField('event_id');
                echo $this->form->renderField('total_places', 'params');
                echo $this->form->renderField('attendeetype', 'params');
                echo HTMLHelper::_('uitab.endTab');

                echo HTMLHelper::_('uitab.addTab', 'myTab', 'User access', Text::_('User access'));
                echo $this->form->renderField('maxattendees', 'params');
                echo $this->form->renderField('waitinglist', 'params');
                echo $this->form->renderField('userlistvisibletousers', 'params');
                echo HTMLHelper::_('uitab.endTab');

                echo HTMLHelper::_('uitab.addTab', 'myTab', 'guest', Text::_('Guest access'));
                echo $this->form->renderField('guest', 'params');
                echo $this->form->renderField('telephone_required', 'params');
                echo $this->form->renderField('maxguestattendees', 'params');
                echo $this->form->renderField('userlistvisibletoguests', 'params');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'payments', Text::_('Payments'));
                echo $this->form->renderField('payment_required', 'params');
                echo $this->form->renderField('payment_details', 'params');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'comms', Text::_('Comms'));
                echo $this->form->renderField('booking_contact_id', 'params');
                echo $this->form->renderField('email_booking', 'params');
                echo $this->form->renderField('notrecommended', 'params');
                echo $this->form->renderField('email_waiting', 'params');
                echo $this->form->renderField('send_both_contacts', 'params');
                echo $this->form->renderField('email_format', 'params');
                echo $this->form->renderField('customsignature', 'params');
                echo $this->form->renderField('signature', 'params');
                echo $this->form->renderField('closingoption', 'params');
                echo $this->form->renderField('customclosingdate', 'params');
                echo $this->form->renderField('send_booking_list_onclosed', 'params');
                echo $this->form->renderField('task_comment', 'params');
                echo $this->form->renderField('walk_leader_id', 'params');

                echo HTMLHelper::_('uitab.endTab');

                echo HTMLHelper::_('uitab.addTab', 'myTab', 'event', Text::_('Event Details'));
                $ebRecord = helper::getEVB($this->item, 'Internal');
                if ($ebRecord !== null) {
                    echo $ebRecord->displayEventData();
                    echo $ebRecord->displayBookingTable();
                    echo $ebRecord->displayWaitingTable();
                } else {
                    echo '<h3>Event data not available</h3>';
                }
                echo HTMLHelper::_('uitab.endTab');

                echo HTMLHelper::_('uitab.endTabSet');
                ?>
                <div class="control-group">
                    <div class="controls">

                        <?php if ($this->canSave): ?>
                            <button type="submit" class="validate btn btn-primary">
                                <span class="fas fa-check" aria-hidden="true"></span>
                                <?php echo Text::_('JSUBMIT'); ?>
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-danger link-button granite"
                           href="<?php echo Route::_('index.php?option=com_ra_eventbooking&task=eventsettingform.cancel'); ?>"
                           title="<?php echo Text::_('JCANCEL'); ?>">
                            <span class="fas fa-times" aria-hidden="true"></span>
                            <?php echo Text::_('JCANCEL'); ?>
                        </a>
                    </div>
                </div>

                <input type="hidden" name="option" value="com_ra_eventbooking"/>
                <input type="hidden" name="task"
                       value="eventsettingform.save"/>
                       <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>
