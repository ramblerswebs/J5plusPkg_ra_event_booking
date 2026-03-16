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
use Joomla\CMS\Layout\LayoutHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_eventbooking&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="eventsetting-form" class="form-validate form-horizontal">


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

        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>
    <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

    <input type="hidden" name="jform[state]" value="<?php echo isset($this->item->state) ? $this->item->state : ''; ?>" />

    <?php echo $this->form->renderField('modified_by'); ?>


    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

    <input type="hidden" name="task" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>