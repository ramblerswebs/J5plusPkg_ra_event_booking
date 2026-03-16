<?php

/*
 * Waiting
 *      parameters
 *         POST data
 *             id - id of event
 *       
 *      url
 *         index.php?option=com_ra_eventbooking&view=waiting&format=json
 * 
 * 
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Waiting;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;

// use Joomla\CMS\Component\ComponentHelper;
// No direct access
defined('_JEXEC') or die;

class JsonView extends BaseJsonView {

    public function display($tpl = null) {

        try {
            $feedback = [];

            $data = helper::getPostedData();
            $ewid = $data->ewid;
            $ebRecord = helper::getEVBrecord($ewid, "Internal");
            $waitingList = $ebRecord->options->waitinglist;
            if (!$waitingList) {
                throw new \RuntimeException('A notification list is not enabled for this event');
            }
            $bookingData = $data->bookingData;
            $id = $bookingData->id;
            $name = $bookingData->name;
            $email = $bookingData->email;
            if ($id > 0) {
                $juser = \JFactory::getUser();
                $email = $juser->email;
                $name = $juser->name;
            }

            $item = $ebRecord->wlc->getItemByMd5Email(md5($email));
            if ($item === null) {
                $item = helper::getNewWaiting($id, $name, $email, "Internal");
                $ebRecord->wlc->addItem($item);
                $feedback[] = '<h3>We have added you to the list and will notify you when places become available</h3>';
                $emailTemplate = 'waitingadd.html';
            } else {
                $ebRecord->removeWaiting(md5($email));
                $feedback[] = '<h3>We have removed you from the list, so you will not receive any further notifications</h3>';
                $emailTemplate = 'waitingdelete.html';
            }
            $ebRecord->updateDatabase('Waiting');
            $to = [$item];
            $replyTo = $ebRecord->getEventContact();
            if ($ebRecord->options->email_booking === 'individual') {
                if ($ebRecord->options->email_waiting) {
                    $copyTo = helper::getEventContacts($ebRecord);
                }
            }
            $copyTo = null;
            $title = $ebRecord->getEmailTitle('NOTIFY');
            $content = helper::getEmailTemplate($emailTemplate, $ebRecord);
            helper::sendEmailsToUser($to, $copyTo, $replyTo, $title, $content);
            if ($ebRecord->options->email_booking === 'list') {
                if ($ebRecord->options->email_waiting) {
                    helper::sendBookingListUpdate($ebRecord, 'WAITING CHANGE');
                }
            }
            $record = (object) [
                        'feedback' => $feedback
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
