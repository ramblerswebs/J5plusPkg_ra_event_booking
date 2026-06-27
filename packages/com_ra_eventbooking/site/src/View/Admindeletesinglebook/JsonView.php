<?php

/*
 * Delete a booking 
 *      Parameters
 * 
 *        ewid - id of event
 *        userMd5email - the email
 *        
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=Admindeletesinglebook&format=json
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

namespace Ramblers\Component\Ra_eventbooking\Site\View\Admindeletesinglebook;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Component\ComponentHelper;

// No direct access
defined('_JEXEC') or die;

class JsonView extends BaseJsonView {

    public function display($tpl = null) {
        try {
            $feedback = [];
            $data = helper::getPostedData();
            $md5Email = $data->md5Email;
            $ewid = $data->ewid;

            $ebRecord = helper::getEVBrecord($ewid, "Internal");
            $placesBefore = $ebRecord->noOfPlaces();

            $ebRecord->removeBooking($md5Email);
            $ebRecord->updateDatabase('Booking');
            $feedback[] = '<h3>The booking for this event has been removed</h3>';
            // do we need to notify waiting list
            $ebRecord->sendEmailToWaitingList($placesBefore);
            // return status of booking
            $record = (object) [
                        'feedback' => $feedback
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
