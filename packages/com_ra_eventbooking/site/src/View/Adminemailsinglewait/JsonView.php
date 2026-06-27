<?php

/*
 * Admin email mail single waiting
 *      parameters
 *         POST data
 *             id - id of event
 *             event - json version of walk/event Ramblers-webs format
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=adminemailsinglewait&format=json
 * 
 * 
 */

namespace Ramblers\Component\Ra_eventbooking\Site\View\Adminemailsinglewait;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
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

            $to = [$ebRecord->wlc->getItemByMd5Email($data->to->md5Email)];
            $replyTo = $ebRecord->getEventContact();
            $copyTo = $replyTo;
            $mailTemplate = 'email_waiting';
            $fields = $ebRecord->getAllEmailFields();
            $fields['EMAILCONTENT'] = $data->emailContent;
            helper::sendEmailsToUser($to, $copyTo, $replyTo, $mailTemplate, $fields);
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
