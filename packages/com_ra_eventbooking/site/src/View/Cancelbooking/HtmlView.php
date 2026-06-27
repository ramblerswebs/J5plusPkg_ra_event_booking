<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_eventbooking
 * @author     Chris Vaughan  <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2025 Ruby Tuesday
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Cancelbooking;

// No direct access
defined('_JEXEC') or die;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

/**
 * View class to cancel a user's booking.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView {

    protected $state;
    protected $item;
    protected $form;
    protected $params;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        $app = Factory::getApplication();
        $input = $app->input;
        $ewid = $input->getString('id', 0);
        $md5Email = $input->getString('cancel', 0);
        $ebRecord = helper::getEVBrecord($ewid, "Internal");
        if ($ebRecord === null) {
            echo '<h3>Sorry we could not find this event</h3>';
            return;
        }
        $placesBefore = $ebRecord->noOfPlaces();

        $currentBooking = $ebRecord->blc->getItemByMd5Email($md5Email);
        $ok = $ebRecord->removeBooking($md5Email);
        if (!$ok) {
            echo '<h3>Sorry we could not find your booking, it has not been cancelled</h3>';
            return;
        }
        $ebRecord->updateDatabase('Booking');
        echo '<h3>Your booking for this event has been cancelled</h3>';

        // send user and booking contacts email
        $this->emailUser($ebRecord, $currentBooking);

        // do we need to notify waiting list
        $ebRecord->sendEmailToWaitingList($placesBefore);
    }

    private function emailUser($ebRecord, $currentBooking) {
        // send user and booking contact email
        $mailTemplate = 'remove_booking';
        $to[] = $currentBooking;
        $replyTo = $ebRecord->getEventContact();
        $copyTo = null;
        if ($ebRecord->options->email_booking === 'individual') {
            $copyTo = $ebRecord->getEventContacts();
        }
        $fields = $ebRecord->getAllEmailFields();
        helper::sendEmailsToUser($to, $copyTo, $replyTo, $mailTemplate, $fields);
        helper::sendBookingListUpdate($ebRecord);
    }
}
