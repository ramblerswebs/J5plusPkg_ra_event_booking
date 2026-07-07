<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_eventbooking
 * @author     Chris Vaughan  <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2025 Ruby Tuesday
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_eventbooking\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Eventsetting model.
 *
 * @since  1.0.0
 */
class EventsettingModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_RA_EVENTBOOKING';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.0
     */
    public $typeAlias = 'com_ra_eventbooking.eventsetting';

    /**
     * @var    null  Item data
     *
     * @since  1.0.0
     */
    protected $item = null;

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   1.0.0
     */
    #[\Override]
    public function getTable($type = 'Eventsetting', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.0
     */
    #[\Override]
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();

        // Get the form.
        $form = $this->loadForm(
                'com_ra_eventbooking.eventsetting',
                'eventsetting',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // CRITICAL: Load the params fieldset
        if (!$form->getFieldset('params')) {
            $form->loadFile('params', false);  // Load from same XML
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.0
     */
    #[\Override]
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState(
                'com_ra_eventbooking.edit.eventsetting.data',
                []
        );

        if (empty($data)) {
            $data = $this->getItem();
            if ($data) {
                // Ensure params is Registry
                $registry = new \Joomla\Registry\Registry($data->params);
                $data->params = $registry;
            }
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   1.0.0
     */
    #[\Override]
    public function getItem($pk = null) {

        if ($item = parent::getItem($pk)) {
            //  if (isset($item->params)) {
            //      $item->params = json_encode($item->params);
            //  }
            // Do any procesing on fields here if needed
            // 1. Global component params
            $globalParams = ComponentHelper::getParams('com_ra_eventbooking');  // Registry
            // 2. Item params
            $itemParams = new Registry($item->params); // or your JSON field
            // 3. Effective params: item overrides global; "" means “use global”
            $effective = clone $globalParams;
            $effective->merge($itemParams);
        }

        return $item;
    }

    /**
     * Method to duplicate an Eventsetting
     *
     * @param   array  &$pks  An array of primary key IDs.
     *
     * @return  boolean  True if successful.
     *
     * @throws  Exception
     */
    public function duplicate(&$pks) {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $dispatcher = $this->getDispatcher();

// Access checks.
        if (!$user->authorise('core.create', 'com_ra_eventbooking')) {
            throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $context = $this->option . '.' . $this->name;

// Include the plugins for the save events.
        PluginHelper::importPlugin($this->events_map['save']);

        $table = $this->getTable();

        foreach ($pks as $pk) {

            if ($table->load($pk, true)) {
// Reset the id to create a new record.
                $table->id = 0;

                if (!$table->check()) {
                    throw new \Exception($table->getError());
                }


// Create the before save event.
                $beforeSaveEvent = AbstractEvent::create(
                        $this->event_before_save,
                        [
                            'context' => $context,
                            'subject' => $table,
                            'isNew' => true,
                            'data' => $table,
                        ]
                );

// Trigger the before save event.
                $dispatchResult = Factory::getApplication()->getDispatcher()->dispatch($this->event_before_save, $beforeSaveEvent);

// Check if dispatch result is an array and handle accordingly
                $result = isset($dispatchResult['result']) ? $dispatchResult['result'] : [];

// Proceed with your logic
                if (in_array(false, $result, true) || !$table->store()) {
                    throw new \Exception($table->getError());
                }

// Trigger the after save event.
                Factory::getApplication()->getDispatcher()->dispatch(
                        $this->event_after_save,
                        AbstractEvent::create(
                                $this->event_after_save,
                                [
                                    'context' => $context,
                                    'subject' => $table,
                                    'isNew' => true,
                                    'data' => $table,
                                ]
                        )
                );
            } else {
                throw new \Exception($table->getError());
            }
        }

// Clean cache
        $this->cleanCache();

        return true;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    #[\Override]
    protected function prepareTable($table) {

        if (empty($table->id)) {
// Set ordering to the last item if not set
            if (@$table->ordering === '') {
                $db = $this->getDatabase();
                $db->setQuery('SELECT MAX(ordering) FROM #__ra_event_bookings');
                $max = $db->loadResult();
                $table->ordering = $max + 1;
            }
        }
    }

    #[\Override]
    public function save($data) {
        // $data['mydemo']['params'] is auto-converted to JSON by Table
        return parent::save($data);
    }
}
