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
 * Main page
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_textcleanup\output\searchtable;
use tool_textcleanup\utils;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('./search_control_form.php');

admin_externalpage_setup('tooltextcleanup');

$search = optional_param('search', '', PARAM_RAW);
$types = optional_param_array('types', [], PARAM_RAW);

$pagetitle = get_string('searchandcleanup', 'tool_textcleanup');

// Set up the page.
$url = new moodle_url("/admin/tool/textcleanup/index.php", array('search' => $search));
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$output = $PAGE->get_renderer('tool_textcleanup');
$PAGE->requires->js_call_amd('tool_textcleanup/selectall', 'init', array());

// The data reload
$isloading = get_config('tool_textcleanup', 'isloadingdata');
$currentlabel = $isloading ? get_string('dataloading', 'tool_textcleanup')
    : get_string('reloaddata', 'tool_textcleanup');

$loaddatabutton = new single_button(new moodle_url('#'), $currentlabel);
$loaddataformid = 'loaddataform';
$loaddatabutton->formid = $loaddataformid;

// The data cleanup button
$cleanupdataform = new single_button(new moodle_url('#'), get_string('cleanupdata', 'tool_textcleanup'));
$cleanupdataform->class = 'singlebutton bg-danger p-1 m-1';
$cleanupdataformid = 'cleanupdataform';
$cleanupdataform->formid = $cleanupdataformid;


if (!$search) {
    $search = 'document.createElement'; // This is the mark of a trojan
    // <img src=\"URLOFTHESITE/\"
    // onerror=\"var s=document.createElement(&quot;script&quot;)...
}

$form = new search_control_form(null, ['types' => utils::get_all_types()]);
$form->set_data(['search' => $search]);

$searchtable = new searchtable($search, $types);
$searchtable->baseurl = $url;
$searchtable->setup();
$searchtable->query_db();

$PAGE->requires->js_call_amd('tool_textcleanup/async_data_manager', 'init', array(
    $loaddataformid,
    $cleanupdataformid,
    (bool) $isloading,
    $search,
    $types,
    $searchtable->totalrows
));

echo $output->header();
echo $output->heading($pagetitle);

echo $form->render();

echo $output->heading(get_string('actions', 'tool_textcleanup'), 3);
echo $output->box_start('generalbox d-flex');
echo $output->render($loaddatabutton);
echo $output->render($cleanupdataform);
echo $output->box_end();

echo $output->render($searchtable);

echo $output->footer();
