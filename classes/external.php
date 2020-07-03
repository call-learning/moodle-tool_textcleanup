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
 * Utils for renderable
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_textcleanup;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->libdir/externallib.php");

use core\session\exception;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * This is the external API for this tool. Used mainly to build the table offline.
 *
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns description of build_text_table() parameters.
     *
     * @return external_function_parameters
     */
    public static function build_text_table_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Load the temporary table with content from all text fields in the database.
     */
    public static function build_text_table() {
        $params = self::validate_parameters(self::build_text_table_parameters(),
            array());
        try {
            set_config('isloadingdata', true, 'tool_textcleanup');
            utils::build_temp_results();
        } finally {
            set_config('isloadingdata', false, 'tool_textcleanup');
        }
        return [];
    }

    /**
     * Returns description of build_text_table() result value.
     *
     * @return external_description
     */
    public static function build_text_table_returns() {
        return new external_single_structure(array());
    }

    /**
     * Cleanup all text content from a subset of the data matched by the search box.
     *
     * @return external_function_parameters
     */
    public static function cleanup_text_parameters() {
        return new external_function_parameters(array(
            'query' => new external_value(PARAM_RAW, 'Search query', VALUE_OPTIONAL, ""),
            'types' => new external_multiple_structure(
                new external_value(PARAM_ALPHAEXT, 'Type', VALUE_OPTIONAL, "")),
            'maxrecords' => new external_value(PARAM_INT, 'Maximum records to cleanup', VALUE_OPTIONAL, 0),
        ));
    }

    /**
     * Load the temporary table with content from all text fields in the database.
     */
    public static function cleanup_text($searchquery = "", $types = [], $maxrecords = 0) {
        $params = self::validate_parameters(self::cleanup_text_parameters(),
            ['query' => $searchquery, 'types' => $types, 'maxrecords' => $maxrecords]);
        $recordnums = utils::cleanup_text($searchquery, $types, $maxrecords);
        return ['cleanedrecords' => $recordnums];
    }

    /**
     * Returns description of list_templates() result value.
     *
     * @return external_description
     */
    public static function cleanup_text_returns() {
        return new external_single_structure(array(
            'cleanedrecords' => new external_value(PARAM_INT, 'Record that have been cleaned up'),
        ));
    }

}
