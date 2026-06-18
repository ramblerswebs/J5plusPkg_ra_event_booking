<?php

/*
 *  email booking contact
 *      parameters
 *         POST data
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=emailbookingcontact&format=json
 * 
 * 
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Emailbookingcontact;

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
            $data = helper::getPostedData();
            $ewid = $data->ewid;
            $from = $data->from;

            $ebRecord = helper::getEVBrecord($ewid, "Internal");
            $to = $ebRecord->getEventContact();
            if ($from->id > 0) {
                $juser = Factory::getUser($from->id);
                $name = $juser->name;
                $email = $juser->email;
            } else {
                $name = $from->name;
                $email = $from->email;
            }
            $replyTo = (object) [
                        'name' => $name,
                        'email' => $email,
            ];

            $copy = $replyTo;

            $mailTemplate = 'email_booking_contact';
            $fields = helper::getAllEmailFields($ebRecord);
            $fields['EMAILCONTENT'] = $data->emailContent;
            helper::sendEmailfromUser($to, $copy, $replyTo, $mailTemplate, $fields);

            $feedback[] = '<h3>Your email has been sent to the booking contact, ' . $to->name . '</h3>';
            $record = (object) [
                        'feedback' => $feedback
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
