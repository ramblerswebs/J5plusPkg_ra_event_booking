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
use Joomla\CMS\Table\Extension;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;

/**
 * Class Ra_eventbookingFrontendHelper
 *
 * @since  1.0.0
 */
class Ra_eventbookingHelper {

    // path of attachments
    private static $attachmentFile = null;
    private static $currentURL = null;

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
        self::$currentURL = $data->currentURL;
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
        return new evb($data, $mode);
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
            return new evb($result, $mode);
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
            $item = new evb($row, 'Summary');
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

//    public static function sendBookingChangeEmail($evb, $md5Email, $cancel) {
//
//        $mailTemplate = 'remove_booking';
//        $attach = null;
//        $currentBooking = $evb->blc->getItemByMd5Email($md5Email);
//
//        // send email confirmation
//        $to[] = $currentBooking;
//        $replyTo = $evb->getEventContact();
//        $copyTo = null;
//        if ($evb->options->email_booking === 'individual') {
//            $copyTo = $evb->getEventContacts();
//        }
//
//        $fields = helper::getAllEmailFields($evb, $md5Email);
//        helper::sendEmailsToUser($to, $copyTo, $replyTo, $mailTemplate, $fields, $attach);
//        helper::sendBookingListUpdate($evb);
//    }

    public static function getAllEmailFields($evb, $md5Email = null) {
        $ewid = $evb->event_id;
        $fields = $evb->getEmailFields();
        $app = Factory::getApplication();
        $fields['SITENAME'] = $app->get('sitename');   // gets Global Configuration → Site Name
        $fields['SITEURL'] = Uri::base(false);
        if ($md5Email !== null) {
            $fields['CANCELURL'] = $fields['SITEURL'] . '?option=com_ra_eventbooking&view=cancelbooking&id=' . $ewid . '&cancel=' . $md5Email;
            $fields['CANCELBUTTON'] = "<a href='" . $fields['CANCELURL'] . "' style='border-radius: 5px; background-color:  #F08050; color: white;padding: 3px;margin:3px;'>CANCEL Booking</a>";
        }
        $fields['LOCALPOPUPURL'] = self::$currentURL . '&walkid=' . $ewid;
        $fields['VIEWBUTTON'] = "<a href='" . $fields['LOCALPOPUPURL'] . "' style='border-radius: 5px; background-color:  #9BC8AB; color: white;padding: 3px 3px;margin:3px;'>View Event</a>";
        $fields['FOOTER'] = " <p><small>This email is an automated one sent from the web site:" . $fields['SITENAME'] . " on behalf of " . $fields['GROUPNAME'] . "<br>You are being sent this email as you have either booked places or you are on the waiting list for one of our events.</small></p>";
        return $fields;
    }

    public static function sendBookingListUpdate($evb) {
        if ($evb->options->email_booking !== 'list') {
            return;
        }
        $to = $evb->getEventContacts();
        $copyTo = null;
        $replyTo = null;
        $bookinglist = $evb->blc->getBookingTable($evb->options->payment_required, true);
        $waitinglist = $evb->getWaitingTable(true);
        $mailTemplate = 'email_booking_list';
        $fields = helper::getAllEmailFields($evb);
        $fields['BOOKINGLIST'] = $bookinglist;
        $fields['WAITINGLIST'] = $waitinglist;
        $fields['REASON'] = "A booking or the waiting list entry has been updated";
        self::sendEmailsToUser($to, $copyTo, $replyTo, $mailTemplate, $fields);
    }

    public static function uniqueByProperty(array $objects, string $property): array {
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

    public static function sendToWaitingList($evb) {
        $to = $evb->wlc->getArray();
        $replyTo = $evb->getEventContact();

        $noOfPlaces = $evb->noOfPlaces();
        if ($noOfPlaces < 1) {
            throw new \RuntimeException('Email to waiting list: no places available');
        }
        $mailTemplate = 'notify_list_email';
        $fields = helper::getAllEmailFields($evb);
        helper::sendEmailsToUser($to, null, $replyTo, $mailTemplate, $fields);
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

    public static function sendEmailsToUser($sendToArray, $copy, $replyTo, $template, $fields, $attach = null) {

        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

        // Load the administrator language file so mail template strings are available
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_ra_eventbooking', JPATH_ADMINISTRATOR);

        foreach ($sendToArray as $sendTo) {
            $mailer->ClearAllRecipients();
            $fields["TONAME"] = $sendTo->name;
            $fields["TOEMAIL"] = $sendTo->email;
            if (method_exists($sendTo, 'noAttendees')) {
                $fields["ATTENDEES"] = $sendTo->noAttendees();
            } else {
                $fields["ATTENDEES"] = '???';
            }
            if ($replyTo !== null) {
                $fields["REPLYTONAME"] = $replyTo->name;
            } else {
                $fields["REPLYTONAME"] = 'Unknown';
            }
            // Create the mail template instance
            $langTag = Factory::getApplication()->getLanguage()->getTag(); // returns e.g. 'en-GB'
            $mailTemplate = new MailTemplate('com_ra_eventbooking.' . $template, $langTag, $mailer);
            // Supply the tag values - keys must match the tags defined in your SQL params
            $mailTemplate->addTemplateData($fields);

            // Add the recipient/copy and replyto
            $mailTemplate->addRecipient($sendTo->email, $sendTo->name);
            if ($copy !== null) {
                if (is_array($copy)) {
                    foreach ($copy as $item) {
                        $mailTemplate->addRecipient($item->email, $item->name, 'cc');
                    }
                } else {
                    $mailTemplate->addRecipient($copy->email, $copy->name, 'cc');
                }
            }
            if ($replyTo !== null) {
                $mailer->addReplyTo($replyTo->email, $replyTo->name);
            }
            if ($attach->type) {
                if ($attach->type === 'string') {
                    helper::addStringAttachment($mailer, $attach);
                }
            }

            // Send
            try {
                $mailTemplate->send();
                if (self::$attachmentFile !== null) {
                    @unlink(self::$attachmentFile);
                    self::$attachmentFile = null;
                }
            } catch (\Exception $e) {
                // Get the full chain
                $msg = $e->getMessage();
                $prev = $e->getPrevious();
                while ($prev) {
                    $msg .= ' | CAUSED BY: ' . $prev->getMessage();
                    $prev = $prev->getPrevious();
                }
                throw new \RuntimeException('Error sending email:' . $msg);
            }
        }
    }

    public static function sendEmailfromUser($sendTo, $copy, $replyTo, $template, $fields) {
        $config = Factory::getConfig();
        $sender = array(
            $config->get('mailfrom'),
            $config->get('fromname')
        );
        $fields['TONAME'] = $sendTo->name;
        $fields['TOEMAIL'] = $sendTo->email;
        $fields['REPLYTONAME'] = $replyTo->name;

        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

        // Load the administrator language file so mail template strings are available
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_ra_eventbooking', JPATH_ADMINISTRATOR);
        // Create the mail template instance
        $langTag = Factory::getApplication()->getLanguage()->getTag(); // returns e.g. 'en-GB'
        $mailTemplate = new MailTemplate('com_ra_eventbooking.' . $template, $langTag, $mailer);
        $mailTemplate->addTemplateData($fields);

        $mailTemplate->addRecipient($sendTo->email, $sendTo->name);
        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo->email, $replyTo->name);
        }

        $mailTemplate->addRecipient($sendTo->email, $sendTo->name);
        if ($copy !== null) {
            $mailer->addCC($copy->email, $copy->name);
        }

        // Send
        try {
            $send = $mailTemplate->send();
            if (!$send) {
                Log::add('Unable to send email to ' . $sendTo->name, Log::ERROR, 'com_ra_eventbooking');
            }
        } catch (\Exception $e) {
            // Get the full chain
            $msg = $e->getMessage();
            $prev = $e->getPrevious();
            while ($prev) {
                $msg .= ' | CAUSED BY: ' . $prev->getMessage();
                $prev = $prev->getPrevious();
            }
            Factory::getApplication()->enqueueMessage($msg, 'error');
        }
    }

    private static function addStringAttachment($mailer, $attach) {

        $filename = $attach->filename;
        $encoding = $attach->encoding;
        $mimeType = $attach->mimeType;
        $contents = $attach->data;

        // Get Joomla tmp path 
        $tmpPath = Factory::getApplication()->get('tmp_path');
        $tmpPath = $tmpPath . "/walkcalendar";
        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0755, true);
        }
        // Build a unique filename
        $file = $tmpPath . '/cal' . uniqid() . '.ics';

        // Write the string into the file
        file_put_contents($file, $contents);

        // Now $file is a real file you can attach:
        $mailer->addAttachment($file, $filename, $encoding, $mimeType);

        // Optionally delete after sending:
        helper::$attachmentFile = $file;
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
        // do not change  $options->payment_required 
        $options->total_places = intval($options->total_places);
        $options->telephone_required = $options->telephone_required === '1';
        $options->userlistvisibletousers = $options->userlistvisibletousers === '1';
        $options->userlistvisibletoguests = $options->userlistvisibletoguests === '1';
        $options->waitinglist = $options->waitinglist === '1';
        $options->email_waiting = $options->email_waiting === '1';
        $options->send_both_contacts = $options->send_both_contacts === '1';
        $options->send_booking_list_onclosed = $options->send_booking_list_onclosed === '1';
        $options->walk_leader_id = intval($options->walk_leader_id);
        // do not change $options->bookingemailtextrequired 

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

    //   private $helper;

    public function __construct($value, $mode) {
        //  $this->helper = $helper;
        if ($value === null) {
            throw new \RuntimeException('Invalid EVB information [null]');
        }

        $this->event_id = $value->event_id;
        if ($this->event_id === null) {
            throw new \RuntimeException('Invalid EVB information [null event id]');
        }

        $this->blc = new blc();
        $this->blc->process($value->booking_data, $mode);

        $this->wlc = new wlc();
        $this->wlc->process($value->waiting_data, $mode);

        $event = new eventData();
        $event->process($value->event_data);
        $this->event_data = $event;
        $this->params = $value->params; // save raw params for send email on closed
        $this->options = $this->processParams($value->params);
        $this->actualClosingDate = $this->getClosingDate();
    }

    private function processParams($jsonparams) {
        $globals = helper::getRawGlobals();
        $options = \json_decode($jsonparams);

        foreach ($options as $key => $value) {
            if (property_exists($globals, $key)) {
                if ($options->$key === "") {
                    $options->$key = $globals->$key;
                }
            }
        }
        // payment_required and bookingemailtextrequired need converting to boolean
        // to pass to js code
        // never store options.

        switch ($options->payment_required) {
            case 'global':
                $options->payment_required = $globals->payment_required === '1';
                if ($globals->payment_required === '1') {
                    $options->payment_details = $globals->payment_details;
                } else {
                    $options->payment_details = '';
                }
                break;
            case 'no':
                $options->payment_required = false;
                $options->payment_details = '';
                break;
            case 'yes':
                $options->payment_required = true;
                break;
        }
        switch ($options->bookingemailtextrequired) {
            case 'global':
                $options->bookingemailtextrequired = $globals->bookingemailtextrequired === '1';
                if ($globals->bookingemailtextrequired === '1') {
                    $options->bookingemailtext = $globals->bookingemailtext;
                } else {
                    $options->bookingemailtext = '';
                }
                break;
            case 'no':
                $options->bookingemailtextrequired = false;
                $options->bookingemailtext = '';
                break;
            case 'yes':
                $options->bookingemailtextrequired = true;
                break;
        }
        return helper::fixParams($options);
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

    public function getEventContacts($includeWalkLeader = false) {
        $emails = [];
        $ec = $this->getEventContact();
        $emails[] = helper::getSendTo($ec->name, $ec->email);
        if ($this->options->send_both_contacts) {
            $gc = helper::getGroupContact();
            $emails[] = helper::getSendTo($gc->name, $gc->email);
        }
        if ($includeWalkLeader or $this->isBookingClosed()) {
            $wl = $this->getWalkLeader();
            if ($wl !== null) {
                $emails[] = helper::getSendTo($wl->name, $wl->email);
            }
        }
        return helper::uniqueByProperty($emails, 'email');
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

    public function getEmailFields() {
        $fields = [];
        $totalPlacesAvailable = $this->options->total_places;
        $fields['PLACESAVAILABLE'] = $this->options->total_places;
        if ($totalPlacesAvailable === 0) {
            $totalPlacesAvailable = 'Unlimited places available';
            $placesAvailable = 'Unlimited';
            $placesTaken = $this->blc->noAttendees();
        } else {
            $placesAvailable = $totalPlacesAvailable - $placesTaken;
            $placesTaken = $this->blc->noAttendees();
        }
        $fields['PLACESTAKEN'] = $placesTaken;
        $noOfPlaces = $this->noOfPlacesText();
        $fields['TOTALPLACESAVAILABLE'] = $totalPlacesAvailable;
        $fields['NOOFPLACES'] = $noOfPlaces;
        $fields['SIGNATURE'] = 'Regards<br/><br/>' . $this->options->booking_contact_name;

        if ($this->options->payment_required) {
            $fields['PAYMENT'] = "<p>Please note that a payment is required for this event and your booking is not valid unless you follow the instructions below</p>"
                    . $this->options->payment_details;
        } else {
            $fields['PAYMENT'] = '';
        }
        $fields['BOOKINGEMAILTEXT'] = $this->options->bookingemailtext;

        $fields['BOOKINGLIST'] = $this->blc->getBookingTable($this->options->payment_required, true);
        $fields['WAITINGLIST'] = $this->getWaitingTable(true);

        //  $fields['reason'] = "A booking or the waiting list entry has been updated";
        $fields['EVENTID'] = $this->event_id;

        $fields = $this->event_data->addEmailFields($fields);
        return $fields;
    }

    public function sendEmailBookingOnClosed() {
        // sent to booking contacts     
        $to = $this->getEventContacts(true);
        $replyTo = null;
        $bookinglist = $this->blc->getBookingTable($this->options->payment_required, true);
        $waitinglist = $this->getWaitingTable(true);
        $mailTemplate = 'email_booking_list_closed';
        $fields = self::getAllEmailFields($this);
        $fields['BOOKINGLIST'] = $bookinglist;
        $fields['WAITINGLIST'] = $waitinglist;
        helper::sendEmailsToUser($to, null, $replyTo, $mailTemplate, $fields);
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
    //  do not $localPopupUrl as this changes if the website menu structure changes
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

    public function addEmailFields($fields) {
        $date = new \DateTime($this->date);
        $fields["DATE"] = $date->format("D, jS M Y");
        $fields["YYYY/MM/DD"] = $date->format('Y/m/d');
        $fields["YY/MM/DD"] = $date->format('y/m/d');
        $fields["TITLE"] = $this->title;
        $fields["GROUPNAME"] = $this->groupName;
        $fields["EVENTID"] = $this->id;
        $fields["EVENTTYPE"] = strtolower($this->eventType);
        $fields["EVENTDATE"] = $date->format("D, jS M Y");
        $fields["EVENTTITLE"] = $this->title;
        $fields["EVENTDESCRIPTION"] = $this->descriptionHtml;
        $fields["NATIONALURL"] = $this->nationalUrl;
        $fields["DATEUPDATED"] = $this->dateUpdated;
        return $fields;
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
            'nationalUrl' => $this->nationalUrl
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
