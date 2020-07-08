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
 * Update and upgrade
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool always true
 */
function xmldb_tool_textcleanup_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2020030704) {

        // Rename field useridinvolved on table tool_textcleanup_temp to NEWNAMEGOESHERE.
        $table = new xmldb_table('tool_textcleanup_temp');
        $field = new xmldb_field('useridmodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'contextid');

        // Launch rename field useridinvolved.
        $dbman->rename_field($table, $field, 'useridinvolved');

        // Textcleanup savepoint reached.
        upgrade_plugin_savepoint(true, 2020030704, 'tool', 'textcleanup');
    }

    return true;
}
