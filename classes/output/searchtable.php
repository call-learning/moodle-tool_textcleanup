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
 * Renderable table for the search and replace actions
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_textcleanup\output;

use coding_exception;
use context;
use core_php_time_limit;
use core_user;
use html_writer;
use renderable;
use stdClass;
use table_sql;
use tool_textcleanup\utils;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Renderable table for the search
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class searchtable extends table_sql implements renderable {

    protected $types = [];
    protected $search = "";

    const PAGE_SIZE = 10;

    public function __construct($search, $types = []) {
        parent::__construct('searchandreplacetext');

        $this->set_attribute('class', 'reportlog generaltable generalbox');
        $this->search = $search;
        $this->types = $types;
        // Add course column if logs are displayed for site.
        $cols = array();
        $headers = array();
        $this->build_sql_search();

        // Select.
        if (!$this->is_downloading()) {
            $cols[] = 'select';
            $headers[] = get_string('select') .
                '<div class="selectall"><label class="accesshide" for="selectall">' . get_string('selectall') . '</label>
                    <input type="checkbox" id="selectall" name="selectall" title="' . get_string('selectall') . '"/></div>';
        }
        $this->define_columns(array_merge($cols, array('type', 'label', 'location', 'usermodified',
            'datemodified', 'content')));
        $this->define_headers(array_merge($headers, array(
                get_string('type', 'tool_textcleanup'),
                get_string('label', 'tool_textcleanup'),
                get_string('location', 'tool_textcleanup'),
                get_string('usermodified', 'tool_textcleanup'),
                get_string('datemodified', 'tool_textcleanup'),
                get_string('content', 'tool_textcleanup'),
                get_string('actions', 'tool_textcleanup'),
            )
        ));
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(true);
    }

    /**
     * Generate the time column.
     *
     * @param stdClass $row data.
     * @return string HTML for the datemodified column
     * @throws coding_exception
     */
    public function col_datemodified($row) {
        return $this->convert_time($row->datemodified);
    }

    /**
     * Generate the content column. This is the content but we remove the javascript or
     * potentially dangerous code.
     *
     * @param stdClass $row data.
     * @return string HTML for the content column
     * @throws coding_exception
     */
    public function col_content($row) {
        return htmlEntities(clean_text($row->content), ENT_QUOTES);
    }

    /**
     * Generate the label column option
     *
     * @param stdClass $row data.
     * @return string HTML for the label columns.
     */
    public function col_label($row) {
        return clean_text($row->label);
    }

    /**
     * Generate the time action column.
     *
     * @param stdClass $row data.
     * @return string HTML for the action button columns.
     */
    public function col_actions($row) {
        return '';
    }

    public function col_usermodified($row) {
        return $row->useridmodified ? fullname(core_user::get_user($row->useridmodified)) : '';
    }

    /**
     * @param $row
     * @return string
     * @throws coding_exception
     */
    public function col_select($row) {
        $selectcol = '<label class="accesshide" for="selectentity_' . $row->id . '">';
        $selectcol .= get_string('selectentity', 'tool_textcleanup', $row->id);
        $selectcol .= '</label>';
        $selectcol .= '<input type="checkbox" class="selectentity"
                              id="selectentity_' . $row->id . '"
                              name="selectedentity"
                              value="' . $row->entityid . '"/>';
        $selectcol .= '<input type="hidden"
                              name="selectentity_type_' . $row->id . '"
                              value="' . $row->type . '"/>';
        return $selectcol;
    }

    /**
     * Get the link and description for the entity location
     *
     * @param stdClass $row data.
     * @return string HTML for the content column
     * @throws coding_exception
     */
    public function col_location($row) {
        if ($row->contextid) {
            $context = context::instance_by_id($row->contextid);
            if ($context->get_url()) {
                return html_writer::link($context->get_url(), $context->get_url());
            }
        }
        return "";
    }

    /**
     * Convert time to displayable text
     *
     * @param string $timestamp timestamp data.
     * @return string HTML for the time column
     * @throws coding_exception
     */
    protected function convert_time($timestamp) {
        if (empty($this->download)) {
            $dateformat = get_string('strftimedatetime', 'core_langconfig');
        } else {
            $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');
        }
        return userdate($timestamp, $dateformat);
    }

    protected function build_sql_search() {
        // Very similar to db_replace function.
        global $DB;
        list($searchparams, $searchsql) = utils::get_temptable_search_sql($this->search, $this->types);
        $fields = utils::get_fields_temp_table();
        $fieldlist = $DB->sql_concat_join("'_'", array('su.type', 'su.entityid')) . " AS id, su." . implode(', su.', $fields);
        $this->set_sql($fieldlist,
            "{" . utils::TEMP_SEARCHTABLE . "} su",
            $searchsql,
            $searchparams);
    }

    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar. Bar
     * will only be used if there is a fullname column defined for the table.
     */
    public function query_db($pagesize = self::PAGE_SIZE, $useinitialsbar = false) {
        parent::query_db($pagesize, $useinitialsbar);

    }

    public function get_sql_where() {
        return array([], []);
    }

    public function out($pagesize = self::PAGE_SIZE, $useinitialsbar = false, $downloadhelpbutton = '') {
        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }
}