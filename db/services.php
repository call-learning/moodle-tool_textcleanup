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
 * Services
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'tool_textcleanup_build_text_table' => array(
        'classname' => 'tool_textcleanup\external',
        'methodname' => 'build_text_table',
        'description' => 'Build a the temporary table content to enable search through all text.',
        'type' => 'write',
        'capabilities' => 'tool/textcleanup:rebuildtable',
        'ajax' => true,
    ),
    'tool_textcleanup_cleanup_text' => array(
        'classname' => 'tool_textcleanup\external',
        'methodname' => 'cleanup_text',
        'description' => 'Cleanup text from all selected entities.',
        'type' => 'write',
        'capabilities' => 'tool/textcleanup:cleanuptext',
        'ajax' => true,
    ),
    'tool_textcleanup_get_count_search' => array(
        'classname' => 'tool_textcleanup\external',
        'methodname' => 'get_search_count',
        'description' => 'Get the search count for the searched text.',
        'type' => 'write',
        'capabilities' => 'tool/textcleanup:count',
        'ajax' => true,
    )
);
