<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for quickpoll_grade_item_update() in lib.php.
 *
 * @package    mod_quickpoll
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll;

/**
 * Tests for quickpoll_grade_item_update().
 *
 * @covers ::quickpoll_grade_item_update
 */
final class lib_grade_item_update_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Fetches the grade_item for the given instance, requiring it to exist.
     *
     * @param \stdClass $instance Activity instance.
     * @return \grade_item
     */
    private function fetch_grade_item(\stdClass $instance): \grade_item {
        return \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quickpoll',
            'iteminstance' => $instance->id,
            'itemnumber' => 0,
            'courseid' => $instance->course,
        ]);
    }

    /**
     * Regression test: grade_update() (lib/gradelib.php) silently drops any
     * 'gradepass' key in the $itemdetails array it is given — its own internal
     * allow-list does not include it — so a configured pass grade must be applied
     * directly on the grade_item instead. Before the fix, this assertion failed with
     * gradepass staying at 0.0 no matter what the instance configured.
     *
     * @return void
     */
    public function test_gradepass_is_applied_to_the_grade_item(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quickpoll');
        $instance = $generator->create_instance(['course' => $course->id, 'maxgrade' => 100]);
        $instance->gradepass = 60;

        $result = quickpoll_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $gradeitem = $this->fetch_grade_item($instance);
        $this->assertEqualsWithDelta(60.0, (float) $gradeitem->gradepass, 0.001);
    }

    /**
     * Regression test: quickpoll_add_instance() never set $data->id before calling
     * quickpoll_grade_item_update($data), so the grade item created at activity
     * creation time had an undefined (null) iteminstance — completely disconnected
     * from the real activity. A later save (quickpoll_update_instance(), which does
     * set $data->id first) would then create a second, correctly-linked item,
     * leaving the first one orphaned in grade_items forever.
     *
     * @return void
     */
    public function test_add_instance_grade_item_is_linked_to_the_real_instance(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quickpoll');
        $instance = $generator->create_instance(['course' => $course->id, 'maxgrade' => 100]);

        $gradeitem = $this->fetch_grade_item($instance);

        $this->assertNotEmpty($gradeitem);
        $this->assertSame((int) $instance->id, (int) $gradeitem->iteminstance);
    }
}
