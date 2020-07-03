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
 * Renderer class for textcleanup tool.
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_textcleanup\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use table_sql;

/**
 * Renderer class for textcleanup tool.
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the search table.
     *
     * @param table_sql $table
     * @return string HTML
     */
    public function render_searchtable(table_sql $table) {

        ob_start();
        $table->out();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }
}
