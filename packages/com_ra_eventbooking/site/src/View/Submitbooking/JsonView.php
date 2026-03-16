<?php

/*
 * Submit a booking
 *      Parameters
 * 
 *        item - the id of the event 
 *        attendees - no of attendees
 *      if user is not logged on and guest bookings are allowed
 *        name - name of person making booking
 *        email - email address of person making booking
 *        
 * 
 *      url
 *         index.php?option=com_ra_eventbooking&view=submitbooking&format=json
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

namespace Ramblers\Component\Ra_eventbooking\Site\View\Submitbooking;

use Joomla\CMS\Uri\Uri;
use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Component\ComponentHelper;

// No direct access
defined('_JEXEC') or die;

class JsonView extends BaseJsonView {

    public function display($tpl = null) {
        // Input user details and number of attendees
        // check if user has existing booking
        // remove from waiting list if necessary 
        // update booking list and waiting list
        // send email to booker and contact

        try {

            $feedback = [];
            $data = helper::getPostedData();
            $ewid = $data->ewid;
            $attach = new \stdClass();
            $attach->data = $data->ics;
            $attach->type = 'string';
            $attach->encoding = 'base64';
            $attach->filename = 'walk.ics';
            $attach->mimeType = 'text/calendar';
            $bookingData = $data->bookingData;
            if ($bookingData->id > 0) {
                $juser = \JFactory::getUser($bookingData->id);
                $bookingData->email = $juser->email;
                $bookingData->name = $juser->name;
            }
            // retrieve current booking data
            $ebRecord = helper::getEVBrecord($ewid, "Internal");
            if ($ebRecord === null) {
                throw new \RuntimeException('Invalid input: event not found');
            }
            $guest = $ebRecord->options->guest;
            $maxattendees = $ebRecord->options->maxattendees;
            $maxguestattendees = $ebRecord->options->maxguestattendees;

            self::checkInput($guest, $maxattendees, $maxguestattendees, $bookingData);
            $id = $bookingData->id;
            $name = $bookingData->name;
            $email = $bookingData->email;
            $telephone = $bookingData->telephone;
            $member = $bookingData->member;
            $attendees = $bookingData->attendees;
            $paid = $bookingData->paid;
            if ($member !== 'Yes' && $ebRecord->options->attendeetype === 'memonly') {
                throw new \RuntimeException('Booking rejected, event is only open to Ramblers Members');
            }
            $newBooking = helper::getNewBooking($id, $name, $email, $telephone, $member, $attendees, $paid, "Internal");

            $ebRecord->checkBooking($newBooking);
            $ebRecord->updateBooking($newBooking);
            $ebRecord->updateDatabase('Booking');
            if ($newBooking->noAttendees() > 0) {
                $feedback[] = '<h3>You have been booked on this event</h3>';
            } else {
                $feedback[] = '<h3>Your booking for this event has been removed/cancelled</h3>';
            }

            $isWaiting = $ebRecord->wlc->isWaiting($email);
            if ($isWaiting !== null) {
                $ebRecord->wlc->remove($email);
                $ebRecord->updateDatabase('Waiting');
                $feedback[] = '<h3>We have removed you from the waiting list</h3>';
            }
            if ($newBooking->noAttendees() > 0) {
                $this->emailUserBooking($ebRecord, $newBooking, $attach);
            } else {
                $this->emailUserCancelled($ebRecord, $newBooking);
            }

            $record = (object) [
                        'feedback' => $feedback
            ];
            echo new JsonResponse($record);
        } catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }

    private function emailUserBooking($ebRecord, $currentBooking, $attach) {
        $emailTemplate = 'newbooking.html';
        $action = 'BOOKING';
        $ewid = $ebRecord->event_id;
        $email = $currentBooking->email;
        $to[] = $currentBooking;
        $replyTo = $ebRecord->getEventContact();
        $copyTo = null;
        if ($ebRecord->options->email_booking === 'individual') {
            $copyTo = helper::getEventContacts($ebRecord);
        }
        $title = $ebRecord->getEmailTitle($action);
        $content = helper::getEmailTemplate($emailTemplate, $ebRecord);
        $siteUrl = Uri::root();
        $cancelURL = $siteUrl . '?option=com_ra_eventbooking&view=cancelbooking&id=' . $ewid . '&cancel=' . md5($email);
        $content = str_replace('{cancelUrl}', $cancelURL, $content);
        helper::sendEmailsToUser($to, $copyTo, $replyTo, $title, $content, $attach);
        helper::sendBookingListUpdate($ebRecord);
    }

    private function emailUserCancelled($ebRecord, $currentBooking) {
        // send user and booking contacts email
        $emailTemplate = 'removebooking.html';
        $attach = null;
        $to[] = $currentBooking;
        $replyTo = $ebRecord->getEventContact();
        $copyTo = null;
        if ($ebRecord->options->email_booking === 'individual') {
            $copyTo = helper::getEventContacts($ebRecord);
        }
        $title = $ebRecord->getEmailTitle('CANCEL');
        $content = helper::getEmailTemplate($emailTemplate, $ebRecord);
        helper::sendEmailsToUser($to, $copyTo, $replyTo, $title, $content, $attach);
        helper::sendBookingListUpdate($ebRecord);
    }

    private static function checkInput($guest, $maxattendees, $maxguestattendees, $bookingData) {
        $juser = \JFactory::getUser($bookingData->id);
        $canEdit = helper::canEdit();
        if (!$canEdit) {
            if ($juser->id !== $bookingData->id) {
                throw new \RuntimeException('Invalid user details: you may have been logged out of web site');
            }
        }
        if (!$guest && $bookingData->id === 0) {
            throw new \RuntimeException('No guest bookings allowed');
        }
        if ($bookingData->id > 0) {
            if ($bookingData->attendees > $maxattendees) {
                throw new \RuntimeException('Booking exceeds number allowed');
            }
        } else {
            if (strlen($bookingData->name) === 0) {
                throw new \RuntimeException('Invalid user details: name');
            }
            if (strlen($bookingData->email) === 0) {
                throw new \RuntimeException('Invalid user details: email');
            }
            if ($bookingData->attendees > $maxguestattendees) {
                throw new \RuntimeException('Booking exceeds number allowed');
            }
        }
    }
}
