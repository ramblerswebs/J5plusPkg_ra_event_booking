<?php

/*
 * @package    Com_Ra_eventbooking
 * @author     Chris Vaughan  <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2025 Ruby Tuesday
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 
 * EW     an RA event or walk in ramblers library format
 * ESC    a collection of booking records , EVB
 * EVB    a booking record for an event,  an object
 * NBI    a new booking information for one user
 * BLC    a collection of bookings, collection of BLI
 * BLI    the user information booking for a user
 * WLC    a collection of waiting records, collection of WLI
 * WLI    the user information about someone on waiting list
 */

namespace Ramblers\Component\Ra_eventbooking\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
//use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Table\Extension;

/**
 * Class Ra_eventbookingFrontendHelper
 *
 * @since  1.0.0
 */
class Ra_eventbookingHelper {

    /**
     * Gets the files attached to an item
     *
     * @param   int     $pk     The item's id
     *
     * @param   string  $table  The table's name
     *
     * @param   string  $field  The field's name
     *
     * @return  array  The files
     */
    public static function getFiles($pk, $table, $field) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
                ->select($field)
                ->from($table)
                ->where('id = ' . (int) $pk);

        $db->setQuery($query);

        return explode(',', $db->loadResult());
    }

    /**
     * Gets the edit permission for an user
     *
     * @param   mixed  $item  The item
     *
     * @return  bool
     */
    public static function canUserEdit($item) {
        $permission = false;
        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.edit', 'com_ra_eventbooking') || (isset($item->created_by) && $user->authorise('core.edit.own', 'com_ra_eventbooking') && $item->created_by == $user->id) || $user->authorise('core.create', 'com_ra_eventbooking')) {
            $permission = true;
        }

        return $permission;
    }

    public static function loadScripts() {
        self::addStyleSheet("media/com_ra_eventbooking/css/style.css");
        self::addScript("media/com_ra_eventbooking/js/ra.bookings.js");
        self::addScript("media/com_ra_eventbooking/js/ra.bookings.displayEvents.js");
        self::addScript("media/com_ra_eventbooking/js/ra.bookings.general.js");
        self::addScript("media/com_ra_eventbooking/js/ra.bookings.form.js");
        self::addScript("media/com_ra_eventbooking/js/blue/md5.min.js");
        self::addScript("https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js");
        self::addStyleSheet("https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css", "text/css");
    }

    public static function addScript($path, $type = "text/javascript") {
        if (!str_starts_with($path, "http")) {
            $filemtime = filemtime($path);
        } else {
            $filemtime = 0;
        }
        $document = Factory::getDocument();
        $document->addScript($path . "?rev=" . $filemtime, array('type' => $type));
    }

    public static function addStyleSheet($path, $type = "text/css") {
        if (!str_starts_with($path, "http")) {
            $filemtime = filemtime($path);
        } else {
            $filemtime = 0;
        }
        $document = Factory::getDocument();
        $document->addStyleSheet($path . "?rev=" . $filemtime, array('type' => $type));
    }

    public static function getPostedData() {
        $input = Factory::getApplication()->getInput();
        // all posted data is within the data field
        $jsonData = $input->POST->get('data', '', 'raw');
        $data = \json_decode($jsonData);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON data received.');
        }
        return $data;
    }

    public static function getEventsWithBooking() {
        // return array of ids for active booking records
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $names = array('event_id');
        $query->select($db->quoteName($names));
        $query->from($db->quoteName('#__ra_event_bookings'));
        $query->where($db->quoteName('state') . ' = 1 ');

        $db->setQuery($query);
        $result = $db->loadColumn();
        return $result;
    }

    public static function getNewBooking($id, $name, $email, $telephone, $member, $attendees, $paid, $mode) {
        return new bli($id, $name, $email, $telephone, $member, $attendees, $paid, $mode);
    }

    public static function getNewWaiting($id, $name, $email, $mode) {
        return new wli($id, $name, $email, $mode);
    }

    public static function getEVB($data, $mode) {
        return new evb(self::class, $data, $mode);
    }

    public static function getEVBrecord($ewid, $mode) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $names = array('event_id', 'booking_data', 'waiting_data', 'event_data',
            'params'
        );
        $query->select($db->quoteName($names));
        $query->from($db->quoteName('#__ra_event_bookings'));
        $query->where($db->quoteName('state') . ' = 1 ');
        $query->where($db->quoteName('event_id') . ' = ' . $ewid . ' ');

        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result !== null) {
            return new evb(self::class, $result, $mode);
        }
        return null;
    }

    public static function getAllEVBRecords() {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $names = array('event_id', 'booking_data', 'waiting_data', 'event_data',
            'params');
        $query->select($db->quoteName($names));
        $query->from($db->quoteName('#__ra_event_bookings'));
        $query->where($db->quoteName('state') . ' = 1 ');
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        $items = [];
        foreach ($rows as $row) {
            $item = new evb(self::class, $row, 'Summary');
            array_push($items, $item);
        }
        return $items;
    }

    public static function updateDBField($event_id, $field, $value, $type = "string") {
        $varType = \Joomla\Database\ParameterType::STRING;
        switch ($type) {
            case 'int':
                $varType = \Joomla\Database\ParameterType::INTEGER;
                break;
            case 'string':
                $varType = \Joomla\Database\ParameterType::STRING;
                break;
            default:
                throw new \RuntimeException('App error in updateDBField');
        }
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

// Fields to update.
        $fields = array(
            $db->quoteName($field) . ' = :field'
        );

// Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('event_id') . ' = :event_id'
        );

        $query->update($db->quoteName('#__ra_event_bookings'))->set($fields)->where($conditions);

        $query
                ->bind(':field', $value, $varType)
                ->bind(':event_id', $event_id, \Joomla\Database\ParameterType::STRING);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result) {
            throw new \RuntimeException('Unknown error while updating database');
        }
    }

    public static function sendBookingChangeEmail($evb, $md5Email, $cancel) {

        $emailTemplate = 'removebooking.html';
        $action = 'CANCEL';
        $attach = null;
        $currentBooking = $evb->blc->getItemByMd5Email($md5Email);

        // send email confirmation
        $to[] = $currentBooking;
        $replyTo = $evb->getEventContact();
        $copyTo = null;
        if ($evb->options->email_booking === 'individual') {
            $copyTo = helper::getEventContacts($evb);
        }
        $title = $evb->getEmailTitle($action);
        $content = helper::getEmailTemplate($emailTemplate, $evb);
        $cancelURL = $siteUrl . '?option=com_ra_eventbooking&view=cancelbooking&id=' . $ewid . '&cancel=' . md5($email);
        $content = str_replace('{cancelUrl}', $cancelURL, $content);
        helper::sendEmailsToUser($to, $copyTo, $replyTo, $title, $content, $attach);

        helper::sendBookingListUpdate($evb);
    }

    public static function sendBookingListUpdate($evb) {
        if ($evb->options->email_booking !== 'list') {
            return;
        }
        $action = 'BOOKING CHANGE';
        $to = self::getEventContacts($evb);
        $copy = null;
        $replyTo = null;
        $bookinglist = $evb->blc->getBookingTable($evb->options->payment_required, true);

        $waitinglist = $evb->getWaitingTable(true);

        $title = $evb->getEmailTitle($action);
        $content = self::getEmailTemplate('emailbookinglist.html', $evb);
        $content = str_replace("{bookinglist}", $bookinglist, $content);
        $content = str_replace("{waitinglist}", $waitinglist, $content);
        $content = str_replace("{reason}", "A booking or the waiting list entry has been updated", $content);
        self::sendEmailsToUser($to, $copy, $replyTo, $title, $content);
    }

    public static function getEventContacts($evb, $includeWalkLeader = false) {
        $emails = [];
        $ec = $evb->getEventContact();
        $emails[] = self::getSendTo($ec->name, $ec->email);
        if ($evb->options->send_both_contacts) {
            $gc = self::getGroupContact();
            $emails[] = self::getSendTo($gc->name, $gc->email);
        }
        if ($includeWalkLeader) {
            $wl = $evb->getWalkLeader();
            if ($wl !== null) {
                $emails[] = self::getSendTo($wl->name, $wl->email);
            }
        }
        return self::uniqueByProperty($emails, 'email');
    }

    public static function sendEmailBookingOnClosed($evb) {
        // sent to booking contacts     
        $to = self::getEventContacts($evb, true);
        $replyTo = null;
        $title = $evb->getEmailTitle('BOOKING CLOSED');
        $bookinglist = $evb->blc->getBookingTable($evb->options->payment_required, true);
        $waitinglist = $evb->getWaitingTable(true);
        $content = self::getEmailTemplate('emailbookinglistOnClosed.html', $evb);
        $content = str_replace("{bookinglist}", $bookinglist, $content);
        $content = str_replace("{waitinglist}", $waitinglist, $content);
        self::sendEmailsToUser($to, null, $replyTo, $title, $content);
    }

    private static function uniqueByProperty(array $objects, string $property): array {
        $seen = [];

        return array_values(array_filter($objects, function ($obj) use (&$seen, $property) {
                    $value = $obj->$property;          // adjust if you need a getter
                    if (isset($seen[$value])) {
                        return false;
                    }
                    $seen[$value] = true;
                    return true;
                }));
    }

    public static function resetEmailOnClosed($event) {
        $jsonparams = $event->params;
        $params = \json_decode($jsonparams);
        if ($params === null) {
            $params = new \stdClass();
        }
        $params->send_booking_list_onclosed = '0';
        $event->params = json_encode($params);
        $event->updateDatabase('Params');
    }

    public static function sendToWaitingList($ebRecord) {
        $to = $ebRecord->wlc->getArray();
        $replyTo = $ebRecord->getEventContact();
        $title = $ebRecord->getEmailTitle('PLACES');
        $noOfPlaces = $ebRecord->noOfPlaces();
        if ($noOfPlaces < 1) {
            throw new \RuntimeException('Email to waiting list: no places available');
        }
        $content = helper::getEmailTemplate('notifylistemail.html', $ebRecord);

        helper::sendEmailsToUser($to, null, $replyTo, $title, $content);
    }

    public static function getGroupContact() {
        $componentParams = ComponentHelper::getParams('com_ra_eventbooking');
        $id = $componentParams->get('booking_contact_id', 0);
        If ($id < 1) {
            throw new \RuntimeException('Default Booking Contact not set, set default Options');
        }
        $juser = Factory::getUser($id);
        $user = (object) ['name' => $juser->name,
                    'email' => $juser->email];
        return $user;
    }

    public static function sendEmailsToUser($sendToArray, $copy, $replyTo, $subject, $content, $attach = null) {
        $config = Factory::getConfig();
        $sender = array(
            $config->get('mailfrom'),
            $config->get('fromname')
        );

        $container = Factory::getContainer();
        $mailer = $container->get(MailerFactoryInterface::class)->createMailer();
//$mailer = Factory::getMailer();
        $mailer->isHtml(true);
        $mailer->Encoding = '8bit';
        $mailer->setSender($sender);
        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo->email, $replyTo->name);
        }
        $mailer->setSubject($subject);
        if ($attach !== null) {
            if ($attach->type === 'string') {
                self::addStringAttachment($mailer, $attach);
            }
        }
        foreach ($sendToArray as $sendTo) {
            $mailer->clearAllRecipients();
            $mailer->addRecipient($sendTo->email, $sendTo->name);
            if ($copy !== null) {
                if (is_array($copy)) {
                    foreach ($copy as $item) {
                        $mailer->addCC($item->email, $item->name);
                    }
                } else {
                    $mailer->addCC($copy->email, $copy->name);
                }
            }
            $body = $content;
            $body = str_replace("{toName}", $sendTo->name, $body);
            $body = str_replace("{toEmail}", $sendTo->email, $body);
            $body = str_replace("{attendees}", $sendTo->noAttendees(), $body);
            $body = str_replace("{replyToName}", $replyTo->name, $body);
            $mailer->setBody($body);
            $send = $mailer->Send();
            if (!$send) {
                Log::add('Unable to send email to ' . $sendTo->name, Log::ERROR, 'com_ra_eventbooking');
            }
        }
    }

    public static function sendEmailfromUser($sendTo, $copy, $replyTo, $subject, $content, $attach = null) {
        $config = Factory::getConfig();
        $sender = array(
            $config->get('mailfrom'),
            $config->get('fromname')
        );

        $container = Factory::getContainer();
        $mailer = $container->get(MailerFactoryInterface::class)->createMailer();
        $mailer->isHtml(true);
        $mailer->Encoding = '8bit';
        $mailer->setSender($sender);
        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo->email, $replyTo->name);
        }
        $mailer->setSubject($subject);
        if ($attach !== null) {
            if ($attach->type === 'string') {
                self::addStringAttachment($mailer, $attach);
            }
        }

//    $mailer->clearAllRecipients();
        $mailer->addRecipient($sendTo->email, $sendTo->name);
        if ($copy !== null) {
            $mailer->addCC($copy->email, $copy->name);
        }
        $body = $content;
        $body = str_replace("{toName}", $sendTo->name, $body);
        $body = str_replace("{toEmail}", $sendTo->email, $body);
//     $body = str_replace("{attendees}", $sendTo->noAttendees(), $body);
        $body = str_replace("{replyToName}", $replyTo->name, $body);
        $mailer->setBody($body);
        $send = $mailer->Send();
        if (!$send) {
            Log::add('Unable to send email to ' . $sendTo->name, Log::ERROR, 'com_ra_eventbooking');
        }
    }

    private static function addStringAttachment($mailer, $attach) {
// Your string content, e.g. ICS
        $filename = $attach->filename;
        $encoding = $attach->encoding;
        $mimeType = $attach->mimeType;
        $contents = $attach->data;

// Get Joomla tmp path from configuration
        $config = Factory::getConfig();
        $tmpPath = rtrim($config->get('tmp_path'), '/');  // e.g. /path/to/site/tmp
// Build a unique filename
        $file = $tmpPath . '/ics_' . uniqid() . '.ics';

// Write the string into the file
        file_put_contents($file, $contents);

// Now $file is a real file you can attach:
        $mailer->addAttachment($file, $filename, $encoding, $mimeType);

// Optionally delete after sending:
//@unlink($file);
    }

    public static function getEmailTemplate($template, $evb) {
        $filePath = JPATH_SITE . '/components/com_ra_eventbooking/src/Helper/templates/' . $template;
        $content = \file_get_contents($filePath);
        if (!$content) {
            throw new \RuntimeException('Unable to get content of email:' . $template);
        }
        if ($evb->event_data === null) {
            throw new \RuntimeException('Unable to up date email with event data: ' . $template);
        }
        $content = $evb->event_data->upDateEmail($content);
        $content = $evb->updateEmailPaymentText($content);
        return $evb->updateEmailforBookingInfo($content);
    }

    public static function getUserData() {
        $juser = Factory::getUser();
        $user = (object) ['id' => $juser->id,
                    'name' => $juser->name,
                    'email' => md5($juser->email),
                    'canEdit' => false
        ];
        if ($user->id > 0) {
            $user->canEdit = $juser->authorise('core.edit', 'com_ra_eventbooking');
        }
        return $user;
    }

    public static function getSendTo($name, $email) {
        return new sendTo($name, $email);
    }

    public static function canEdit() {
        $juser = Factory::getUser();
        if ($juser->id > 0) {
            return $juser->authorise('core.edit', 'com_ra_eventbooking');
        }
        return false;
    }

    public static function getRawGlobals() {
        $params = ComponentHelper::getParams('com_ra_eventbooking'); // Registry
        $globals = (object) $params->toArray();
        $id = $globals->booking_contact_id;
        If ($id === 0) {
            throw new \RuntimeException('Group Booking Contact not set - contact group');
        }
        return $globals;
    }

    // used to display global settings on front end for booking contacts
    public static function getGlobals() {
        $globals = self::getRawGlobals();
        self::fixParams($globals);
        return $globals;
    }

    public static function fixParams($options) {
        $juser = Factory::getUser($options->booking_contact_id);
        $options->booking_contact_name = $juser->name;
        $options->booking_contact_md5email = md5($juser->email);
        $options->guest = $options->guest === '1';
        $options->maxattendees = intval($options->maxattendees);
        $options->maxguestattendees = intval($options->maxguestattendees);
        $options->payment_required = $options->payment_required === '1';
        $options->customsignature = $options->customsignature === '1';
        $options->total_places = intval($options->total_places);
        $options->telephone_required = $options->telephone_required === '1';
        $options->userlistvisibletousers = $options->userlistvisibletousers === '1';
        $options->userlistvisibletoguests = $options->userlistvisibletoguests === '1';
        $options->waitinglist = $options->waitinglist === '1';
        $options->email_waiting = $options->email_waiting === '1';
        $options->send_both_contacts = $options->send_both_contacts === '1';
        $options->send_booking_list_onclosed = $options->send_booking_list_onclosed === '1';
        $options->walk_leader_id = intval($options->walk_leader_id);
        return $options;
    }
}

class evb {

    public $event_id;
    public $blc;
    public $wlc;
    public $event_data;
    public $options;
    public $params = null;
    public $actualClosingDate = null;
    private $helper;

    public function __construct($helper, $value, $mode) {
        $this->helper = $helper;
        if ($value === null) {
            throw new \RuntimeException('Invalid EVB information [null]');
        }

        $this->event_id = $value->event_id;

        $this->blc = new blc();
        $this->blc->process($value->booking_data, $mode);

        $this->wlc = new wlc();
        $this->wlc->process($value->waiting_data, $mode);

        $event = new eventData();
        $event->process($value->event_data);
        $this->event_data = $event;
        $this->options = $this->processParams($value->params);
        $this->params = $value->params; // save raw params for send email on closed
        $this->actualClosingDate = $this->getClosingDate();
    }

    private function processParams($jsonparams) {
        $globals = $this->helper::getRawGlobals();
        $options = \json_decode($jsonparams);

        foreach ($options as $key => $value) {
            if (property_exists($globals, $key)) {
                if ($options->$key === "") {
                    $options->$key = $globals->$key;
                }
            }
        }
        return $this->helper::fixParams($options);
    }

    public function checkBooking($newBooking) {
        $currentNoAttendees = $this->blc->noAttendees();
        $extraPlaces = $newBooking->noAttendees();
        // check if user has existing booking
        $currentBooking = $this->blc->hasBooking($newBooking->email);
        if ($currentBooking !== null) {
            $extraPlaces = $extraPlaces - $currentBooking->noAttendees();
        }
        //  calc remaining places
        $totalPlaces = $this->options->total_places;
        If ($totalPlaces === 0) {
            $totalPlaces = PHP_INT_MAX;
        }
        // check booking does not go over total allowed   
        if ($extraPlaces + $currentNoAttendees > $totalPlaces) {
            throw new \RuntimeException('Not enough spare places to make this booking');
        }
    }

    public function updateBooking($newBooking) {
        $bookings = $this->blc;
        $bookings->removeItem($newBooking->email); // remove old booking if there is one
        if ($newBooking->noAttendees() > 0) {
            $bookings->addItem($newBooking);
        }
    }

    public function removeBooking($md5Email) {
        $bookings = $this->blc;
        return $bookings->removeItemByMd5Email($md5Email); // remove old booking if there is one
    }

    public function removeWaiting($md5Email) {
        $waiting = $this->wlc;
        return $waiting->removeItemByMd5Email($md5Email);
    }

    public function displayBookingTable() {
        $juser = Factory::getUser();
        $canEdit = false;
        if ($juser->id > 0) {
            $canEdit = $juser->authorise('core.edit', 'com_ra_eventbooking');
        }
        $userlistvisibletousers = $this->options->userlistvisibletousers;
        $userlistvisibletoguests = $this->options->userlistvisibletoguests;
        $canView = false;
        if ($canEdit) {
            $canView = true;
        }
        if ($userlistvisibletousers && $juser->id > 0) {
            $canView = true;
        }
        if ($userlistvisibletoguests && $juser->id === 0) {
            $canView = true;
        }
        if ($canView) {
            return $this->blc->getBookingTable($this->options->payment_required, $canEdit);
        }
        return '';
    }

    public function getBookingTable($payment_required, $canEdit) {
        return $this->blc->getBookingTable($payment_required, $canEdit);
    }

    public function displayWaitingTable() {
        $juser = Factory::getUser();
        $canEdit = false;
        if ($juser->id > 0) {
            $canEdit = $juser->authorise('core.edit', 'com_ra_eventbooking');
        }
        $userlistvisibletousers = $this->options->userlistvisibletousers;
        $userlistvisibletoguests = $this->options->userlistvisibletoguests;
        $canView = false;
        if ($canEdit) {
            $canView = true;
        }
        if ($userlistvisibletousers && $juser->id > 0) {
            $canView = true;
        }
        if ($userlistvisibletoguests && $juser->id === 0) {
            $canView = true;
        }
        if ($canView) {
            return $this->wlc->getWaitingTable($canEdit);
        }
        return '';
    }

    public function getWaitingTable($canEdit) {
        return $this->wlc->getWaitingTable($canEdit);
    }

    public function getEventContact() {
        $options = $this->options;
        if ($options->booking_contact_id !== 0) {
            $euser = Factory::getUser($options->booking_contact_id);
            $name = $euser->name;
            $email = $euser->email;
            $user = (object) ['name' => $name,
                        'email' => $email];
            return $user;
        }
        throw new \RuntimeException('Group Booking Contact not set - contact group');
    }

    public function getWalkLeader() {
        $options = $this->options;
        if ($options->walk_leader_id !== 0) {
            $euser = Factory::getUser($options->walk_leader_id);
            $name = $euser->name;
            $email = $euser->email;
            $user = (object) ['name' => $name,
                        'email' => $email];
            return $user;
        }
        return null;
    }

    public function createEventData($ew) {
        $ed = new eventData();
        $ed->setValues($ew);
        $this->event_data = $ed;
    }

    public function displayEventData() {
        if ($this->event_data === null OR $this->event_data === '') {
            return '<h3>Walk/Event data not available</h3>';
        }
        return $this->event_data->displayEventData();
    }

    public function isBookingClosed() {
        if ($this->actualClosingDate === null) {
            return false;
        }
        $closing = new \DateTime($this->actualClosingDate);
        $now = new \DateTime();
        return $closing < $now;
    }

    public function noOfPlaces() {
        $maxNo = $this->options->total_places;
        if ($maxNo === 0) {
            return PHP_INT_MAX;
        }
        $attendees = $this->blc->noAttendees();
        return $maxNo - $attendees;
    }

    public function noOfPlacesText() {
        $no = $this->noOfPlaces();
        if ($no > 1000) {
            return 'Unlimited';
        } else {
            return strval($no);
        }
    }

    public function getClosingDate() {
        $which = $this->options->closingoption;
        if ($this->event_data->getDate() === '') {// no event data
            return null;
        }
        $startDate = new \DateTime($this->event_data->getDate());
        $cldate = (clone $startDate);
        switch ($which) {
            case 'start':
                break;
            case '6pm':
                $cldate = $cldate->modify('-1 day')->setTime(18, 0, 0);
                break;
            case '6pmweek':
                $cldate = $cldate->modify('-7 day')->setTime(18, 0, 0);
                break;
            case '7am':
                $cldate = $cldate->setTime(7, 0, 0);
                break;
            case '7amweek':
                $cldate = $cldate->modify('-7 day')->setTime(7, 0, 0);
                break;
            case 'custom':
                return $this->options->customclosingdate;
        }
        return $cldate->format('Y-m-d H:i:s');
    }

    public function getEmailTitle($action) {
        $title = $this->options->email_format;
        if ($title === '') {
            $title = '{yy/mm/dd} {action} {title}';
        }
        return $this->event_data->updateEmailTitle($action, $title);
    }

    public function updateEmailforBookingInfo($content) {

        $totalPlacesAvailable = $this->options->total_places;
        if ($totalPlacesAvailable === 0) {
            $totalPlacesAvailable = 'Unlimited places available';
            $placesAvailable = 'Unlimited';
            $placesTaken = $this->blc->noAttendees();
        } else {
            $placesAvailable = $totalPlacesAvailable - $placesTaken;
            $placesTaken = $this->blc->noAttendees();
        }
        $noOfPlaces = $this->noOfPlacesText();
        if ($this->options->customsignature) {
            $signature = $this->options->signature;
        } else {
            $signature = 'Regards<br/><br/>' . $this->options->booking_contact_name;
        }

        $search = ["{placesAvailable}", "{placesTaken}", "{totalPlacesAvailable}",
            "{noOfPlaces}", "{signature}"];
        $replace = [$placesAvailable, $placesTaken,
            $totalPlacesAvailable, $noOfPlaces, $signature];
        return str_replace($search, $replace, $content);
    }

    public function updateEmailPaymentText($content) {
        $out = '';
        if ($this->options->payment_required) {
            $out = "<p>Please note that a payment is required for this event and your booking is not valid unless you follow the instructions below</p>";
            $out .= $this->options->payment_details;
        }
        return str_replace('{payment}', $out, $content);
    }

    public function updateDatabase($which) {
        $event_id = $this->event_id;
        $varType = \Joomla\Database\ParameterType::STRING;
        switch ($which) {
            case 'Booking':
                $data = json_encode($this->blc);
                $field = 'booking_data';
                break;
            case 'Waiting':
                $data = json_encode($this->wlc);
                $field = 'waiting_data';
                break;
            case 'Event':
                $data = json_encode($this->event_data);
                $field = 'event_data';
                break;
            case 'Params':
                $data = $this->params;
                $field = 'params';
                break;
            default:
                throw new \RuntimeException('Invalid database update request');
        }
//   \updateDBField($ewid, $field, $data, $type);

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
// Fields to update.
        $fields = array(
            $db->quoteName($field) . ' = :field'
        );
// Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('event_id') . ' = :event_id'
        );
        $query->update($db->quoteName('#__ra_event_bookings'))->set($fields)->where($conditions);
        $query
                ->bind(':field', $data, $varType)
                ->bind(':event_id', $event_id, \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);
        $result = $db->execute();
        if (!$result) {
            throw new \RuntimeException('Unknown error while updating database');
        }
    }
}

class blc implements \JsonSerializable {

    private $items = [];

    public function addItem($bli) {
        array_push($this->items, $bli);
    }

    public function removeItem($email) {
        foreach ($this->items as $key => $item) {
            if ($item->email === $email) {
                unset($this->items[$key]);
                return true;
            }
        }
        return false;
    }

    public function getItemByMd5Email($md5email) {
        foreach ($this->items as $key => $item) {
            if ($item->getMd5Email() === $md5email) {
                return $this->items[$key];
            }
        }
        return null;
    }

    public function removeItemByMd5Email($md5email) {
        foreach ($this->items as $key => $item) {
            if ($item->getMd5Email() === $md5email) {
                unset($this->items[$key]);
                return true;
            }
        }
        return false;
    }

    public function getItems() {
        return $this->items;
    }

    public function process($jsonValue, $mode) {
        if ($jsonValue === null) {
            return;
        }
        $values = \json_decode($jsonValue);
        foreach ($values as $value) {
            $this->addItem(new bli($value->id, $value->name, $value->email, $value->telephone, $value->member, $value->noAttendees, $value->paid, $mode));
        }
    }

    public function noAttendees() {
        $no = 0;
        foreach ($this->items as $item) {
            $no += $item->noAttendees();
        }
        return $no;
    }

    public function hasBooking($email) {
        foreach ($this->items as $item) {
            if ($item->email === $email) {
                return $item;
            }
        }
        return null;
    }

    public function getArray() {
        $to = [];
        foreach ($this->items as $item) {
            $to[] = $item;
        }
        return $to;
    }

    public function getBookingTable($payment_required, $canEdit) {
        if (count($this->items) === 0) {
            return "<h3>No bookings at the moment</h3>";
        }
        $out = "<h3>Bookings List</h3>";
        $out .= "<table>";
        $out .= "<thead><tr>";
        $out .= "<th>Name</th>";
        if ($canEdit) {
            $out .= "<th>Status</th>";
            $out .= "<th>Email</th>";
        }
        if ($canEdit) {
            $out .= "<th>Member</th>";
            $out .= "<th>Telephone</th>";
        }
        $out .= "<th>Places</th>";

        if ($payment_required) {
            $out .= "<th>Paid</th>";
        }
        $out .= "</tr></thead>";
        $out .= "<tbody>";

        foreach ($this->items as $item) {
            $out .= $item->getTableRow($payment_required, $canEdit);
        }
        $out .= "</tbody>";
        $out .= "</table>";
        return $out;
    }

    #[\Override]
    public function jsonSerialize(): mixed {
        return $this->items;
    }
}

// Booking
class bli implements \JsonSerializable {

    private $id;
    public readonly string $name;
    public readonly string $email;
    private $telephone;
    private $noAttendees;
    private $paid;
    private $member;
    private $mode; // 'Summary', 'Single' or 'Internal'

    public function __construct($id, $name, $email, $telephone, $member, $attendees, $paid, $mode) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->telephone = $telephone;
        $this->member = $member;
        $this->noAttendees = intval($attendees);
        $this->setPaid($paid);
        $this->mode = $mode;
    }

    public function isPresent($email) {
        return $email === $this->email;
    }

    public function noAttendees() {
        return $this->noAttendees;
    }

    public function setPaid($value) {
        $this->paid = $value;
        if ($value === "") {
            $this->paid = "Zero";
        }
    }

    public function getMd5Email() {
        return md5($this->email);
    }

    public function getTableRow($payment_required, $canEdit) {
        $out = "<tr>";
        $out .= '<td>' . $this->name . '</td>';
        if ($canEdit) {
            if ($this->id > 0) {
                $out .= '<td>Registered</td>';
            } else {
                $out .= '<td>Guest</td>';
            }
            $out .= '<td>' . $this->email . '</td>';
        }
        if ($canEdit) {
            $out .= '<td>' . $this->member . '</td>';
        }
        if ($canEdit) {
            $out .= '<td>' . $this->telephone . '</td>';
        }
        $out .= '<td>' . $this->noAttendees . '</td>';

        if ($payment_required) {
            $out .= '<td>' . $this->paid . '</td>';
        }
        $out .= "</tr>";
        return $out;
    }

    #[\Override]
    public function jsonSerialize(): mixed {
        switch ($this->mode) {
            case "Summary":
                return [
                    "noAttendees" => $this->noAttendees,
                ];
            case "Single":
                return [
                    "id" => $this->id,
                    "name" => $this->name,
                    "md5Email" => md5($this->email),
                    "telephone" => $this->telephone,
                    "noAttendees" => $this->noAttendees,
                    "member" => $this->member,
                    "paid" => $this->paid,
                ];
            case "Internal":
                return [
                    "id" => $this->id,
                    "name" => $this->name,
                    "email" => $this->email,
                    "telephone" => $this->telephone,
                    "noAttendees" => $this->noAttendees,
                    "member" => $this->member,
                    "paid" => $this->paid,
                ];
            default:
                throw new \RuntimeException('Invalid MODE value');
        }
    }
}

class wlc implements \JsonSerializable {

    private $items = [];

    public function addItem($wli) {
        array_push($this->items, $wli);
    }

    public function getItems() {
        return $this->items;
    }

    public function process($jsonValue, $mode) {
        if ($jsonValue === null) {
            return;
        }
        $values = \json_decode($jsonValue);
        foreach ($values as $value) {
            $this->addItem(new wli($value->id, $value->name, $value->email, $mode));
        }
    }

    public function isWaiting($email) {
        foreach ($this->items as $item) {
            if ($item->isWaiting($email)) {
                return $item;
            }
        }
        return null;
    }

    public function remove($email) {
        foreach ($this->items as $key => $item) {
            if ($item->isWaiting($email)) {
                unset($this->items[$key]);
                return true;
            }
        }
        return false;
    }

    public function getItemByMd5Email($md5email) {
        foreach ($this->items as $key => $item) {
            if ($item->getMd5Email() === $md5email) {
                return $this->items[$key];
            }
        }
        return null;
    }

    public function removeItemByMd5Email($md5email) {
        foreach ($this->items as $key => $item) {
            if ($item->getMd5Email() === $md5email) {
                unset($this->items[$key]);
                return true;
            }
        }
        return false;
    }

    public function getArray() {
        $to = [];
        foreach ($this->items as $item) {
            $to[] = $item;
        }
        return $to;
    }

    public function getWaitingTable($canEdit = false) {
        if (count($this->items) === 0) {
            return "";
        }
        $out = "<h3>Waiting List</h3>";
        $out .= "<table>";
        $out .= "<thead><tr>";
        $out .= "<th>Name</th>";
        if ($canEdit) {
            $out .= "<th>Status</th>";
            $out .= "<th>Email</th>";
        }
        $out .= "</tr></thead>";
        $out .= "<tbody>";

        foreach ($this->items as $item) {
            $out .= $item->getTableRow($canEdit);
        }
        $out .= "</tbody>";
        $out .= "</table>";
        return $out;
    }

    #[\Override]
    public function jsonSerialize(): mixed {
        return $this->items;
    }
}

// Waiting list
class wli implements \JsonSerializable {

    private $id;
    public readonly string $name;
    public readonly string $email;
    private $mode;

    public function __construct($id, $name, $email, $mode) {

        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->mode = $mode;
    }

    public function getMd5Email() {
        return md5($this->email);
    }

    public function noAttendees() {
        return 'Waiting list only/Not booked';
    }

    public function isWaiting($email) {
        return $email === $this->email;
    }

    public function getTableRow($canEdit) {
        $out = "<tr>";
        $out .= '<td>' . $this->name . '</td>';
        if ($canEdit) {
            if ($this->id > 0) {
                $out .= '<td>Registered</td>';
            } else {
                $out .= '<td>Guest</td>';
            }
            $out .= '<td>' . $this->email . '</td>';
        }
        $out .= "</tr>";
        return $out;
    }

    #[\Override]
    public function jsonSerialize(): mixed {
        switch ($this->mode) {
            case "Summary":
            case "Single":
                return [
                    "id" => $this->id,
                    "name" => $this->name,
                    "md5Email" => md5($this->email),
                ];
            case "Internal":
                return [
                    "id" => $this->id,
                    "name" => $this->name,
                    "email" => $this->email,
                ];
            default:
                throw new \RuntimeException('Invalid MODE value');
        }
    }
}

class eventData implements \JsonSerializable {

    private $groupName = '';
    private $dateUpdated = '2025-01-01T00:00:00.000Z';
    private $id = '';
    private $eventType = '';
    private $nationalUrl = '';
    private $localPopupUrl = '';
    private $date = '';
    private $title = '';
    private $descriptionHtml = '';

    public function __construct() {
        
    }

    public function setValues($ew) {
        $this->groupName = $ew->admin->groupName;
        $this->dateUpdated = $ew->admin->dateUpdated;
        $this->id = $ew->admin->id;
        $this->eventType = $ew->admin->eventType;
        $this->nationalUrl = $ew->admin->nationalUrl;
        $this->localPopupUrl = $ew->admin->localPopupUrl;
        // walkDate is in UTC 
        $this->date = $this->convertDateTimetoLocal($ew->basics->walkDate);
        $this->title = $ew->basics->title;
        $this->descriptionHtml = $ew->basics->descriptionHtml;
    }

    private function convertDateTimetoLocal($utcString) {
        $utc = new \DateTime($utcString, new \DateTimeZone('UTC'));

        $localTz = new \DateTimeZone('Europe/London'); // or any IANA ID, e.g. 'America/New_York'
        $utc->setTimezone($localTz);

        return $utc->format('Y-m-d H:i:s');        // local time
    }

    public function process($jsonValue) {
        if ($jsonValue === null) {
            return;
        }
        $value = \json_decode($jsonValue);
        if ($value !== '') {
            $this->groupName = $value->groupName;
            $this->dateUpdated = $value->dateUpdated;
            $this->id = $value->id;
            $this->eventType = $value->eventType;
            $this->nationalUrl = $value->nationalUrl;
            $this->localPopupUrl = $value->localPopupUrl;
            $this->date = $value->date;
            $this->title = $value->title;
            $this->descriptionHtml = $value->descriptionHtml;
        }
    }

    public function displayEventData() {
        if ($this->date === '') {
            return '<h3>Event data is not set</h3>';
        }
        $date = new \DateTime($this->date);
        $walkDate = $date->format("D, jS M Y");
        $out = '<table>';
        $out .= '<tr><td>Event type</td><td>' . $this->eventType . '</td></tr>';
        $out .= '<tr><td>Date</th><td>' . $walkDate . '</td></tr>';
        $out .= '<tr><td>Title</th><td>' . $this->title . '</td></tr>';
        $out .= '<tr><td>Description</td><td>' . $this->descriptionHtml . '</td></tr>';
        $out .= '</table>';
        return $out;
    }

    public function upDateEmail($content) {
        $app = Factory::getApplication();
        $siteName = $app->get('sitename');   // gets Global Configuration → Site Name
        $siteUrl = Uri::base(false);

        $date = new \DateTime($this->date);
        $walkDate = $date->format("D, jS M Y");
        $cancelButton = "<a href='{cancelUrl}' style='border-radius: 5px; background-color:  #F08050; color: white;padding: 3px;margin:3px;'>CANCEL Booking</a>";
        $viewButton = "<a href='" . $this->localPopupUrl . "' style='border-radius: 5px; background-color:  #9BC8AB; color: white;padding: 3px 3px;margin:3px;'>View Event</a>";

        $swap = ["{groupName}" => $this->groupName,
            "{eventId}" => $this->id,
            "{eventType}" => strtolower($this->eventType),
            "{eventDate}" => $walkDate,
            "{eventTitle}" => $this->title,
            "{eventDescription}" => $this->descriptionHtml,
            "{nationalUrl}" => $this->nationalUrl,
            "{localPopupUrl}" => $this->localPopupUrl,
            "{cancelButton}" => $cancelButton,
            "{viewButton}" => $viewButton,
            "{siteName}" => $siteName,
            "{siteUrl}" => $siteUrl,
            "{dateUpdated}" => $this->dateUpdated];
        foreach ($swap as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        return $content;
    }

    public function updateEmailTitle($action, $title) {
        $date = new \DateTime($this->date);
        $swap = ["{date}" => $date->format("D, jS M Y"),
            "{yyyy/mm/dd}" => $date->format('Y/m/d'),
            "{yy/mm/dd}" => $date->format('y/m/d'),
            "{action}" => $action,
            "{title}" => $this->title];
        foreach ($swap as $key => $value) {
            $title = str_replace($key, $value, $title);
        }
        return $title;
    }

    public function getDate() {
        return $this->date;
    }

    public function getDateUpdated() {
        return $this->dateUpdated;
    }

    public function setDateUpdated($value) {
        $this->dateUpdated = $value;
    }

    #[\Override]
    public function jsonSerialize(): mixed {
        return [
            "date" => $this->date, // first to allow sort on date
            'dateUpdated' => $this->dateUpdated,
            'id' => $this->id,
            'eventType' => $this->eventType,
            'groupName' => $this->groupName,
            'title' => $this->title,
            'descriptionHtml' => $this->descriptionHtml,
            'nationalUrl' => $this->nationalUrl,
            'localPopupUrl' => $this->localPopupUrl
        ];
    }
}

class sendTo {

    public readonly string $name;
    public readonly string $email;

    public function __construct($name, $email) {
        $this->name = $name;
        $this->email = $email;
    }

    public function noAttendees() {
        return 0;
    }
}
