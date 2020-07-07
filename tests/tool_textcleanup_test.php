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
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_textcleanup_testcase extends advanced_testcase {

    /**
     * Courses
     *
     * @var array
     */
    public $courses = [];
    /**
     * Activities
     *
     * @var array
     */
    public $activities = [];

    /**
     * Blocks
     * @var array
     */
    public $blocks = [];
    /**
     * A sample seemingly dangerous Javascript that can be inserted in a text box
     */
    const DANGEROUS_SCRIPT = '<img src="myimage" onerror="var s=document.createElement(&quot;script&quot;)"/>';

    public function setUp() {
        global $DB;
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

        $config = (object) array(
            'title' => 'Title Block 1',
            'text' => '<a href="view.php?id=82&topic=1"></a>' . self::DANGEROUS_SCRIPT,
            'format' => 1
        );
        $contextcourse = context_course::instance($this->courses[0]->id);

        // There is no generator for block html...

        $blockhtml = (object) array(
            'blockname' => 'html',
            'parentcontextid' => $contextcourse->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'course-view-*',
            'subpagepattern' => null,
            'defaultweight' => '0',
            'configdata' => base64_encode(serialize($config)),
            'timecreated' => time(),
            'timemodified' => time(),
            'defaultregion' => 'side-pre',

        );
        $blockid = $DB->insert_record('block_instances', $blockhtml);

        $this->blocks[] = $DB->get_record('block_instances', array('id' => $blockid));
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
        $this->assertEquals(5, count($searchtable->rawdata));
        $this->assertArrayHasKey('course_' . $this->courses[0]->id, $searchtable->rawdata);
        $this->assertArrayNotHasKey('course_' . $this->courses[1]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('course_' . $this->courses[2]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('forum_' . $this->activities[0]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('resource_' . $this->activities[1]->id, $searchtable->rawdata);
        $this->assertArrayHasKey('block_instances_' . $this->blocks[0]->id, $searchtable->rawdata);
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
        $this->assertEquals(5, count($searchtable->rawdata));

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
        $this->assertEquals(5, $recordcount);
        list($course1, $course2, $forum, $resource, $block) = $this->get_courses_and_modules();
        $this->assertEquals(
            'My summary of course 1:' . self::DANGEROUS_SCRIPT,
            $course1before->summary);
        $this->assertEquals('My summary of course 1:<img src="myimage" alt="myimage" />', $course1->summary);
        $this->assertEquals('My summary of course 2:', $course2->summary);
        $this->assertEquals('Forum summary 1:<img src="myimage" alt="myimage" />', $forum->intro);
        $this->assertEquals('Resource summary 2:<img src="myimage" alt="myimage" />', $resource->intro);
        $blockconfig = unserialize(base64_decode($block->configdata));
        $this->assertEquals('<a href="view.php?id=82&amp;topic=1"></a><img src="myimage" alt="myimage" />',
            $blockconfig->text
        );
    }

    /**
     * Get current course and module information
     *
     * @return array
     * @throws dml_exception
     */
    protected function get_courses_and_modules() {
        global $DB;
        return array(
            $DB->get_record('course', array('id' => $this->courses[0]->id)),
            $DB->get_record('course', array('id' => $this->courses[1]->id)),
            $DB->get_record('forum', array('id' => $this->activities[0]->id)),
            $DB->get_record('resource', array('id' => $this->activities[1]->id)),
            $DB->get_record('block_instances', array('id' => $this->blocks[0]->id))
        );
    }

    /**
     * Test that we display safe HTML through the UI on selected types
     */
    public function test_cleanup_text_filter_type() {
        global $DB;
        $this->resetAfterTest();
        utils::build_temp_results();
        $search = 'document.createElement';
        $course1before = $DB->get_record('course', array('id' => $this->courses[0]->id));

        $recordcount = utils::cleanup_text($search, ['course']);
        $this->assertEquals(2, $recordcount);
        list($course1, $course2, $forum, $resource, $block) = $this->get_courses_and_modules();
        $this->assertEquals(
            'My summary of course 1:' . self::DANGEROUS_SCRIPT,
            $course1before->summary);
        $this->assertEquals('My summary of course 1:<img src="myimage" alt="myimage" />', $course1->summary);
        $this->assertEquals('My summary of course 2:', $course2->summary);
        // We have not cleaned up the forums or resources.
        $this->assertEquals('Forum summary 1:' . self::DANGEROUS_SCRIPT,
            $forum->intro);
        $this->assertEquals('Resource summary 2:' . self::DANGEROUS_SCRIPT,
            $resource->intro);
        $blockconfig = unserialize(base64_decode($block->configdata));
        $this->assertEquals('<a href="view.php?id=82&topic=1"></a>' . self::DANGEROUS_SCRIPT,
            $blockconfig->text
        );
    }

    /**
     * Test that we get the right search count
     */
    public function test_cleanup_text_search_count() {
        $this->resetAfterTest();
        utils::build_temp_results();
        $search = 'document.createElement';
        $recordcount = utils::search_count($search, ['course']);
        $this->assertEquals(2, $recordcount);
    }
}

