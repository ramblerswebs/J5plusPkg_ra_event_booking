<?php

/*
 * Admin email booking list
 *      parameters
 *         POST data
 *             id - id of event
 *             event - json version of walk/event Ramblers-webs format
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=adminemailbookinglist&format=json
 * 
 * 
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Adminemailbookinglist;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Factory;

// use Joomla\CMS\Component\ComponentHelper;
// No direct access
defined('_JEXEC') or die;

class JsonView extends BaseJsonView {

    public function display($tpl = null) {
        try {
            $feedback = [];
            $juser = Factory::getUser();
            $canEdit = false;
            if ($juser->id > 0) {
                $canEdit = $juser->authorise('core.edit', 'com_ra_eventbooking');
            }
            $data = helper::getPostedData();
            $ewid = $data->ewid;
            $ebRecord = helper::getEVBrecord($ewid, "Internal");
            $bookinglist = $ebRecord->getBookingTable($ebRecord->options->payment_required, $canEdit);
            $waitinglist = $ebRecord->getWaitingTable($canEdit);

            $juser = Factory::getUser();
            $to = [helper::getSendTo($juser->name, $juser->email)];

            $replyTo = null;
            $copy = $ebRecord->getEventContact();

            if ($copy->email === $juser->email) {
                $copy = null;
            }

            $title = $ebRecord->getEmailTitle('BOOKING LIST');
            $content = helper::getEmailTemplate('emailbookinglist.html', $ebRecord);
            $content = str_replace("{bookinglist}", $bookinglist, $content);
            $content = str_replace("{waitinglist}", $waitinglist, $content);
            $content = str_replace("{reason}", "", $content);
            helper::sendEmailsToUser($to, $copy, $replyTo, $title, $content);

            $feedback[] = '<h3>Email has been sent</h3>';
            $record = (object) [
                        'feedback' => $feedback
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
