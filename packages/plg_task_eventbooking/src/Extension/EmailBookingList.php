<?php

/**
 * @package     EventBooking.Plugin
 * @subpackage  Task.eventbooking
 *
 * @copyright   (C) 2026 ruby.tuesday@ramblers-webs.org.uk
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Plugin\Task\EventBooking\Extension;

use \Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper as helper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. For Delete Action Logs after x days
 * {@see ExecuteTaskEvent}.
 *
 * @since 0.0.1
 */
final class EmailBookingList extends CMSPlugin implements SubscriberInterface {

    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 0.0.1
     */
    private const TASKS_MAP = [
        'email.bookinglist' => [
            'langConstPrefix' => 'PLG_TASK_EVENTBOOKINGS_EMAIL',
            'method' => 'emailBookingList'
        ],
    ];

    /**
     * @var boolean
     * @since 5.0.0
     */
    protected $autoloadLanguage = true;
    private $debug = false;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 0.0.1
     */
    public static function getSubscribedEvents(): array {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  0.0.1
     * @throws \Exception
     */
    private function emailBookingList(ExecuteTaskEvent $event): int {
        $this->logTask('Start of email booking list');
        try {
            $this->emailBookingListOnClosed();
        } catch (\RuntimeException) {
            // Ignore it
            $this->logTask('Email booking list FAILED');
            return Status::KNOCKOUT;
        }
        $this->logTask('End of email booking list');

        return Status::OK;
    }

    private function emailBookingListOnClosed() {

        $events = helper::getAllEVBRecords();
        foreach ($events as $event) {
            if ($this->debug) {
                $this->logTask('Debug: event ' . $event->event_id);
            }
            if ($event->options->send_booking_list_onclosed) {
                $this->logTask('  Debug: send booking list on closed is on ' . $event->event_id);
                if ($event->isBookingClosed()) {
                    $this->logTask('   Debug: event booking closed: ' . $event->event_id);
                    helper::sendEmailBookingOnClosed($event);
                    $this->logTask('     Debug: email sent: ' . $event->event_id);
                    helper::resetEmailOnClosed($event);

                    $this->logTask('Email sent for event ' . $event->event_id);
                }
            }
        }
        return;
    }
}
