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

use core_php_time_limit;
use dml_exception;

defined('MOODLE_INTERNAL') || die;

/**
 * Class utils
 *
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    /**
     * Get the select query for this table
     * It will return a select that will search in each text field for this table.
     *
     * @param array $columns
     * @param string $table
     * @return string
     */
    public static function get_searchtable_columns_query($columns, $table) {
        global $DB;
        $selectcolumns = [];
        $timecolumn = '';
        $namecolumn = '';
        $useridcolumn = '';

        $locationquery = self::get_location_query($table);

        foreach ($columns as $column) {
            $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
            if ($columnname == 'fullname' || $columnname == 'name') {
                $namecolumn = $columnname;
            }
            if ($columnname == 'userid') {
                $useridcolumn = $columnname;
            }
            switch ($column->meta_type) {
                case 'X':
                case 'C':
                    $selectcolumns[] = $columnname;
                    break;
                case 'I':
                    if (strstr('timemodified', $columnname) ||
                        strstr('timecreated', $columnname)) {
                        if (!$timecolumn || $columnname == 'timemodified') {
                            $timecolumn = $columnname;
                        }
                    }
            }
        }
        if ($selectcolumns) {
            $allcolumns = [];
            $allcolumns[] = "id AS entityid";
            $allcolumns[] = ($useridcolumn ? $useridcolumn : "NULL") . " AS useridinvolved";
            $allcolumns[] = ($timecolumn ? $timecolumn : 0) . " AS datemodified";
            $allcolumns[] = $DB->sql_concat_join("','", $selectcolumns) . " AS content";
            $allcolumns[] = $locationquery;
            $allcolumns[] = "'$table' AS type";
            $allcolumns[] = ($namecolumn ? $namecolumn : "NULL") . " AS label";
            return "SELECT "
                . implode(', ', $allcolumns)
                . " FROM {" . $table . "}";
        }
        return "";
    }

    /**
     * Build temporary table with all text content so to be able to search it through
     *
     * @throws dml_exception
     */
    public static function build_temp_results() {
        // Very similar to db_replace function.
        global $DB;

        // We skip the same tables as in db_replace but add more that would not have any text.
        $skiptables = array('config', 'config_plugins', 'config_log', 'cache_flags', 'course_modules',
            'upgrade_log', 'log', 'external_services', 'externatl_services_functions', 'search_simpledb_index',
            'filter_config', 'sessions', 'events_queue', 'repository_instance_config',
            'block_instances', 'log_queries', 'log_display',
            'stats_daily', 'stats_monthly', 'stats_user_daily',
            'stats_user_monthly', 'stats_user_weekly', 'enrol',
            'analytics_indicator_calc', 'analytics_models', 'analytics_models_log', 'analytics_predictions',
            'analytics_predict_samples', 'analytics_train_samples', 'analytics_used_analysables', 'analytics_used_files',
            'assign_plugin_config', 'backup_controllers', 'block', 'capabilities', 'block_positions', 'question_attempt_steps',
            'question_attempt_step_data', 'logstore_standard_log',
            'tool_textcleanup_temp', '');

        // Turn off time limits, sometimes upgrades can be slow.
        core_php_time_limit::raise();
        $allunionsarray = [];

        if ($tables = $DB->get_tables()) {    // No tables yet at all.
            foreach ($tables as $table) {
                if (in_array($table, $skiptables)) {      // Don't process these.
                    continue;
                }
                if ($columns = $DB->get_columns($table)) {
                    $tableselect = self::get_searchtable_columns_query($columns, $table);
                    if ($tableselect) {
                        $allunionsarray[] = $tableselect;
                    }
                }
            }
        }

        $fields = static::get_fields_temp_table();
        $selectunion = implode(' UNION ALL ', $allunionsarray);
        $fieldlist = "u." . implode(', u.', $fields);

        $insertfieldlist = implode(',', $fields);
        $DB->delete_records(self::TEMP_SEARCHTABLE);

        $sql = "INSERT INTO {" . self::TEMP_SEARCHTABLE . "} ($insertfieldlist) SELECT " .
            "$fieldlist FROM ($selectunion) u";
        $DB->execute($sql); // Load all data into the temp table.
        // Then add data from the blocks.
        self::add_block_table();
    }

    /**
     * Get fields from temptable
     *
     * @return string[]
     */
    public static function get_fields_temp_table() {
        return array(
            "type", "entityid", "label", "contextid", "useridinvolved", "datemodified", "content"
        );
    }

    /**
     * Add html block content
     */
    public static function add_block_table() {
        global $DB;
        $instances = $DB->get_recordset('block_instances', array('blockname' => 'html'));
        $records = [];
        foreach ($instances as $instance) {
            // TODO: intentionally hardcoded until MDL-26800 is fixed.
            $config = unserialize(base64_decode($instance->configdata));

            if (isset($config->text) and is_string($config->text)) {
                $record = new \stdClass();
                $record->type = 'block_instances';
                $record->entityid = $instance->id;
                $record->label = $instance->blockname;
                $record->contextid = $instance->parentcontextid;
                $record->content = $config->text;
                $record->datemodified = $instance->timemodified;
                $records[] = $record;
            }
        }
        $DB->insert_records(self::TEMP_SEARCHTABLE, $records);
        $instances->close();
    }

    /**
     * Seach table name (temporary table to store results).
     */
    const TEMP_SEARCHTABLE = 'tool_textcleanup_temp';

    /**
     * Get the parent location URL for this entity if it exists
     * If it is a module gets the course URL
     *
     * @param string $table
     * @return string
     * @throws dml_exception
     */
    public static function get_location_query($table) {
        global $DB;
        // Check first if this is a module table.
        $locationquery = "NULL AS contextid";
        $moduleid = $DB->get_field('modules', 'id', array('name' => $table));
        if ($moduleid) {
            $locationquery =
                "(SELECT c.id FROM {course_modules} cm
                LEFT JOIN {context} c ON c.instanceid =  cm.id AND c.contextlevel=" . CONTEXT_MODULE . "
                WHERE cm.course=course AND cm.module=$moduleid AND cm.instance = entityid
                LIMIT 1)
                AS contextid";
        } else {
            switch ($table) {
                case 'course':
                    $locationquery =
                        "(SELECT c.id FROM {context} c WHERE c.instanceid =  entityid AND c.contextlevel="
                        . CONTEXT_COURSE . " LIMIT 1)
                AS contextid";
                    break;
                case 'course_categories':
                    $locationquery =
                        "(SELECT c.id FROM {context} c WHERE c.instanceid =  entityid AND c.contextlevel="
                        . CONTEXT_COURSECAT . " LIMIT 1)
                AS contextid";
                    break;
                case 'user':
                    $locationquery =
                        "(SELECT c.id FROM {context} c WHERE c.instanceid =  entityid AND c.contextlevel="
                        . CONTEXT_USER . " LIMIT 1)
                AS contextid";
                    break;
            }
        }
        return $locationquery;
    }

    /**
     * Clean up the matching items using moodle clean_text function
     *
     * @param string $search
     * @param array $types
     * @param int $max
     * @return int
     * @throws \coding_exception
     * @throws dml_exception
     */
    public static function cleanup_text($search = "", $types = [], $max = 0) {
        global $DB;
        if (!$search) {
            return 0;
        }
        list($searchparams, $searchsql) = self::get_temptable_search_sql($search, $types, "");
        $recordset =
            $DB->get_recordset_select(self::TEMP_SEARCHTABLE, $searchsql
                . ' AND wascleaned <> 1', $searchparams, 'type ASC');

        // Order by type.
        $modifiedrecords = 0;
        $table = null;
        $columns = null;
        foreach ($recordset as $rec) {
            if ($max > 0 and $max < $modifiedrecords) {
                break;
            }
            // Only column list if necessary.
            if ($rec->type != $table) {
                $table = $rec->type;
                $columns = $DB->get_columns($table);
            }
            foreach ($columns as $column) {
                $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
                switch ($column->meta_type) {
                    case 'X':
                    case 'C':
                        self::replace_value_in_table($table, $columnname, $rec->entityid);
                        break;
                }

            }
            $rec->wascleaned = 1;
            $DB->update_record(self::TEMP_SEARCHTABLE, $rec);
            $modifiedrecords++;
        }
        $recordset->close();
        return $modifiedrecords;
    }

    /**
     * Replace values in table. Make sure that block instance are treated differently
     *
     * @param string $table
     * @param string $columnname
     * @param int $entityid
     * @throws dml_exception
     */
    protected static function replace_value_in_table($table, $columnname, $entityid) {
        global $DB;
        $oldvalue = $DB->get_field($table, $columnname, array('id' => $entityid));
        if ($table == 'block_instances' && $columnname == 'configdata') {
            $oldvalueconfig = unserialize(base64_decode($oldvalue));
            $oldvalueconfig->text = clean_text($oldvalueconfig->text);
            $cleanvalue = base64_encode(serialize($oldvalueconfig));
        } else {
            $cleanvalue = clean_text($oldvalue);
        }
        if ($oldvalue != $cleanvalue) {
            $DB->set_field($table, $columnname, $cleanvalue, array('id' => $entityid));
        }
    }

    /**
     * Search count
     *
     * @param string $search
     * @param array $types
     * @return int
     * @throws \coding_exception
     * @throws dml_exception
     */
    public static function search_count($search = "", $types = []) {
        global $DB;
        $totalcount = 0;
        if ($search) {
            list($searchparams, $searchsql) = self::get_temptable_search_sql($search, $types, "");
            $totalcount = $DB->count_records_select(self::TEMP_SEARCHTABLE, $searchsql, $searchparams);
        }
        return $totalcount;
    }

    /**
     * Get SQL matchin this search query
     *
     * @param string $search
     * @param array $types
     * @param string $prefix
     * @return array
     * @throws \coding_exception
     * @throws dml_exception
     */
    public static function get_temptable_search_sql($search, $types = [], $prefix = 'su') {
        global $DB;
        $realprefix = $prefix ? "$prefix." : "";
        $columnname = "${realprefix}content";
        $namedparam = 'contentsearch';

        $searchparams = [];
        // Enclose the column name by the proper quotes if it's a reserved word.
        $searchsql = $DB->sql_like($columnname, " :$namedparam");
        $searchparams[$namedparam] = '%' . $DB->sql_like_escape($search) . '%';

        if ($DB->sql_regex_supported()) {
            // If regex supported, then use them.
            $searchsql = $columnname . ' ' . $DB->sql_regex() . " :$namedparam";
            $searchparams[$namedparam] = $search;
        }
        if (!empty($types)) {
            list($typesql, $typesparams) = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED);
            $searchsql .= " AND  ${realprefix}type $typesql";
            $searchparams += $typesparams;
        }
        return array($searchparams, $searchsql);
    }

    /**
     * Get all possible table types
     *
     * @return array
     * @throws dml_exception
     */
    public static function get_all_types() {
        global $DB;
        return $DB->get_fieldset_sql('SELECT DISTINCT type FROM {' . self::TEMP_SEARCHTABLE . '}');
    }
}