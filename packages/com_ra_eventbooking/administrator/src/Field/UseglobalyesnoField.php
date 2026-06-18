<?php

namespace Ramblers\Component\Ra_eventbooking\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

class UseglobalyesnoField extends ListField {

    protected $type = 'Useglobalyesno';

    public function setup(\SimpleXMLElement $element, $value, $group = null) {
        $ok = parent::setup($element, $value, $group);

        // Name of the global param for this field
        $this->globalParam = (string) $element['global_param'];

        return $ok;
    }

    protected function getOptions() {
        $options = parent::getOptions(); // loads your <option> elements (Yes/No)

        $params = ComponentHelper::getParams('com_ra_eventbooking');
        $globalValue = (int) $params->get($this->globalParam, 0);

        $globalLabel = $globalValue === 1 ? Text::_('Use Global Values (Yes)') : Text::_('Use Global Values (No - Not required)');

        $globalOption = new \stdClass();
        $globalOption->value = 'global';        // empty = use global
        $globalOption->text = $globalLabel;

        // Put "Use global ..." at the top
        array_unshift($options, $globalOption);

        return $options;
    }
}
