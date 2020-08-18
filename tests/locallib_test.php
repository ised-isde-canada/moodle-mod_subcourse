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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides {@see mod_subcourse_locallib_testcase} class.
 *
 * @package     mod_subcourse
 * @category    phpunit
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/subcourse/locallib.php');

/**
 * Unit tests for the functions in the locallib.php file.
 */
class mod_subcourse_locallib_testcase extends advanced_testcase {

    /**
     * Test that it is possible to fetch grades from the referenced course.
     */
    public function test_subcourse_grades_update() {

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $metacourse = $generator->create_course();
        $refcourse = $generator->create_course();

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();

        $generator->enrol_user($student1->id, $metacourse->id, 'student');
        $generator->enrol_user($student1->id, $refcourse->id, 'student');
        $generator->enrol_user($student2->id, $metacourse->id, 'student');
        $generator->enrol_user($student2->id, $refcourse->id, 'student');

        // Give some grades in the referenced course.
        $gi = new grade_item($generator->create_grade_item(['courseid' => $refcourse->id]), false);
        $gi->update_final_grade($student1->id, 90, 'test');
        $gi->update_final_grade($student2->id, 60, 'test');
        $gi->force_regrading();
        grade_regrade_final_grades($refcourse->id);

        // Create the Subcourse module instance in the metacourse, representing the final grade in the referenced course.
        $subcourse = $generator->create_module('subcourse', [
            'course' => $metacourse->id,
            'refcourse' => $refcourse->id,
        ]);

        // Fetch all students' grades from the refcourse to the metacourse.
        subcourse_grades_update($metacourse->id, $subcourse->id, $refcourse->id, null, false, false, [], false);

        // Check the grades were correctly fetched.
        $metagrades = grade_get_grades($metacourse->id, 'mod', 'subcourse', $subcourse->id, [$student1->id, $student2->id]);
        $this->assertEquals(90, $metagrades->items[0]->grades[$student1->id]->grade);
        $this->assertEquals(60, $metagrades->items[0]->grades[$student2->id]->grade);

        // Update the grades in the referenced course.
        $gi->update_final_grade($student1->id, 80, 'test');
        $gi->update_final_grade($student2->id, 50, 'test');
        $gi->force_regrading();
        grade_regrade_final_grades($refcourse->id);

        // Fetch again, this time only one student's grades.
        subcourse_grades_update($metacourse->id, $subcourse->id, $refcourse->id, null, false, false, [$student1->id], false);

        // Re-check that the student1's grade was updated succesfully.
        $metagrades = grade_get_grades($metacourse->id, 'mod', 'subcourse', $subcourse->id, [$student1->id, $student2->id]);
        $this->assertEquals(80, $metagrades->items[0]->grades[$student1->id]->grade);
    }
}
