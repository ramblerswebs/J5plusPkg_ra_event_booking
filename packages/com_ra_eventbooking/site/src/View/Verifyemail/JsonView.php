<?php

/*
 * Verify guest email address
 *      Parameters
 * 
 *        name - name of person making booking
 *        email - email address of person making booking
 *        
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=verifyemail&format=json
 * 
 * EW     an RA event or walk in ramblers library format
 * ESC    a collection of booking records , EVB
 * EVB    a booking record for an event, an object
 * NBI    a new booking information for one user
 * BLC    a collection of bookings, collection of BLI
 * BLI    the user information booking for a user
 * WLC    a collection of waiting records, collection of WLI
 * WLI    the user information about someone on waiting list
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Verifyemail;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Component\ComponentHelper;

// No direct access
defined('_JEXEC') or die;

class JsonView extends BaseJsonView {

    public function display($tpl = null) {
        // send email with a 6 digit verification code
        // return md5(code)

        try {
            $feedback = [];
            $data = helper::getPostedData();
            $user = $data->user;
            $name = $user->name;
            $email = $user->email;
            $ewid = $data->ewid;
            $ebRecord = helper::getEVBrecord($ewid, "Internal");

            $code = self::generateCode();

            $feedback[] = '<h3>Verification email sent</h3>';
            $emailTemplate = 'verifyemail.html';

            // send email confirmation

            $to = [helper::getSendTo($name, $email)];
            $replyTo = new \stdClass();
            $replyTo->name = 'NO-REPLY';
            $replyTo->email = 'no-reply@ramblers-webs.org.uk';

            $title = 'Verify your email address - ' . $code;
            $content = helper::getEmailTemplate($emailTemplate, $ebRecord);
            $content = str_replace("{verifyCode}", $code, $content);

            helper::sendEmailsToUser($to, null, $replyTo, $title, $content);

            // return status of booking
            $record = (object) [
                        'feedback' => $feedback,
                        'md5code' => md5($code),
                        'codelength' => strlen($code)
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }

    private static function generateCode() {

        for ($i = 1; $i <= 6; $i++) {
            $code .= strval(rand(0, 9));
        }
        return $code;
    }
}
