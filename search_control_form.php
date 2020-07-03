<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Form for filtering
 */
class search_control_form extends moodleform {

    function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('header', 'filtersettings', get_string('filter', 'tool_textcleanup'));

        // Component.
        $mform->addElement('text', 'search', get_string('searchexpression', 'tool_textcleanup'));
        $mform->setType('search', PARAM_RAW);

        // Types
        if (!empty($customdata) && !empty($customdata['types'])) {
            $typecheckb = array();
            foreach ($customdata['types'] as $type) {
                $typecheckb[] = &
                    $mform->createElement('advcheckbox', $type, '', $type, array('name' => $type, 'group' => 1), $type);
                $mform->setDefault("types[$type]", true);
            }
            $mform->addGroup($typecheckb, 'types', get_string('types', 'tool_textcleanup'));
            $this->add_checkbox_controller(1);
        }

        // Search - submit button.
        $mform->addElement('submit', 'submit', get_string('search', 'tool_textcleanup'));
    }
}

