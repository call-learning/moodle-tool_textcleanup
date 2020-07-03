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
 * Unit test for the tool_textsearch module
 *
 * @package     tool_textcleanup
 * @category    phpunit
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_textcleanup\output\searchtable;
use tool_textcleanup\utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit test for the tool_textsearch module
 *
 * @package     tool_textcleanup
 * @category    phpunit
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_textcleanup_testcase extends advanced_testcase {

    public $courses = [];
    public $activities = [];
    const DANGEROUS_SCRIPT = '<img src="myimage" onerror="var s=document.createElement(&quot;script&quot;)"/>';

    public function Setup() {
        $generator = $this->getDataGenerator();
        $this->setAdminUser();
        $cat1 = $generator->create_category();
        $this->courses[] = $generator->create_course(['category' => $cat1->id,
                'summary' => 'My summary of course 1:' . self::DANGEROUS_SCRIPT,
                'fullname' => 'myfullname c1' . self::DANGEROUS_SCRIPT]
        );
        $cat2 = $generator->create_category(['parent' => $cat1->id]);
        $this->courses[] = $generator->create_course(['category' => $cat2->id,
                'summary' => 'My summary of course 2:',
                'fullname' => 'myfullname c2']
        );
        $cat3 = $generator->create_category();
        $this->courses[] = $generator->create_course(['category' => $cat3->id,
                'summary' => 'My summary of course 3:' . self::DANGEROUS_SCRIPT,
                'fullname' => 'myfullname c3']
        );
        $this->activities[] =
            $generator->create_module('forum',
                ['course' => $this->courses[0]->id, 'intro' => 'Forum summary 1:' . self::DANGEROUS_SCRIPT]);
        $this->activities[] = $generator->create_module('resource',
            ['course' => $this->courses[1]->id, 'intro' => 'Resource summary 2:' . self::DANGEROUS_SCRIPT]);

    }

    /**
     * Test basic building of text datatable and search on it.
     */
    public function test_indexing_contexts() {
        $this->resetAfterTest();
        // Create some courses in categories, and a forum.
        utils::build_temp_results();
        $search = 'document.createElement';
        $searchtable = new searchtable($search);
        $searchtable->baseurl = new moodle_url('/');
        $searchtable->setup();
        $searchtable->query_db(10, false);
        $this->assertEquals(4, count($searchtable->rawdata));
        $this->assertArrayHasKey('course_' . $this->courses[0]->id, $searchtable->rawdata);
        $this->assertArrayNotHasKey('course_' . $this->courses[1]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('course_' . $this->courses[2]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('forum_' . $this->activities[0]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('resource_' . $this->activities[1]->id, $searchtable->rawdata);
    }

    /**
     * Test that we display safe HTML through the UI
     */
    public function test_display_safe() {
        $this->resetAfterTest();
        utils::build_temp_results();
        $search = 'document.createElement';
        $searchtable = new searchtable($search);
        $searchtable->baseurl = new moodle_url('/');
        $searchtable->setup();
        $searchtable->query_db(10, false);
        $this->assertEquals(4, count($searchtable->rawdata));

        $content = $searchtable->col_content(reset($searchtable->rawdata));
        $this->assertNotContains('document.createElement', $content);
    }

    /**
     * Test that we display safe HTML through the UI
     */
    public function test_cleanup_text() {
        global $DB;
        $this->resetAfterTest();
        utils::build_temp_results();
        $search = 'document.createElement';
        $course1before = $DB->get_record('course', array('id' => $this->courses[0]->id));

        $recordcount = utils::cleanup_text($search, []);
        $this->assertEquals(4, $recordcount);
        $course1 = $DB->get_record('course', array('id' => $this->courses[0]->id));
        $course2 = $DB->get_record('course', array('id' => $this->courses[1]->id));
        $forum = $DB->get_record('forum', array('id' => $this->activities[0]->id));
        $resource = $DB->get_record('resource', array('id' => $this->activities[1]->id));
        $this->assertEquals(
            'My summary of course 1:<img src="myimage" onerror="var s=document.createElement(&quot;script&quot;)"/>',
            $course1before->summary);
        $this->assertEquals('My summary of course 1:<img src="myimage" alt="myimage" />', $course1->summary);
        $this->assertEquals('My summary of course 2:', $course2->summary);
        $this->assertEquals('Forum summary 1:<img src="myimage" alt="myimage" />', $forum->intro);
        $this->assertEquals('Resource summary 2:<img src="myimage" alt="myimage" />', $resource->intro);
    }

    /**
     * Test that we display safe HTML through the UI
     */
    public function test_cleanup_text_filter_type() {
        global $DB;
        $this->resetAfterTest();
        utils::build_temp_results();
        $search = 'document.createElement';
        $course1before = $DB->get_record('course', array('id' => $this->courses[0]->id));

        $recordcount = utils::cleanup_text($search, ['course']);
        $this->assertEquals(2, $recordcount);
        $course1 = $DB->get_record('course', array('id' => $this->courses[0]->id));
        $course2 = $DB->get_record('course', array('id' => $this->courses[1]->id));
        $forum = $DB->get_record('forum', array('id' => $this->activities[0]->id));
        $resource = $DB->get_record('resource', array('id' => $this->activities[1]->id));
        $this->assertEquals(
            'My summary of course 1:<img src="myimage" onerror="var s=document.createElement(&quot;script&quot;)"/>',
            $course1before->summary);
        $this->assertEquals('My summary of course 1:<img src="myimage" alt="myimage" />', $course1->summary);
        $this->assertEquals('My summary of course 2:', $course2->summary);
        $this->assertEquals('Forum summary 1:<img src="myimage" alt="myimage" />', $forum->intro);
        $this->assertEquals('Resource summary 2:<img src="myimage" alt="myimage" />', $resource->intro);
    }
}
