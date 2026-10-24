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

namespace availability_oncemet\local;

use availability_completion\condition as completion_condition;
use availability_oncemet\condition;
use core_availability\info;
use core_availability\tree;

/**
 * Availability OnceMet - Unit tests for the availability information of the unlock report.
 *
 * These tests deliberately use the real availability information classes instead of the mock ones
 * of core, as the mock ones hand out the modinfo at any time and would hide what is tested here.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_oncemet\local\report_info
 * @covers     \availability_oncemet\local\report_info_module
 * @covers     \availability_oncemet\local\report_info_section
 */
final class report_info_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    protected \stdClass $course;

    /** @var condition Once met restriction with a nested completion condition. */
    protected condition $restriction;

    /**
     * Creates a course with an activity which a nested completion condition points at.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Gate page']);

        // The completion condition needs the modinfo while it is described, which is what makes it
        // fail when it is described outside of the methods of core.
        $child = completion_condition::get_json($page->cmid, COMPLETION_COMPLETE);
        $this->restriction = new condition(condition::get_json([$child], tree::OP_AND));
    }

    /**
     * Tests that a restriction on an activity is described with the activity it points at.
     */
    public function test_describe_restriction_of_activity(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $info = new report_info_module(get_fast_modinfo($this->course)->get_cm($page->cmid));

        $rendered = info::format_info($info->describe_restriction($this->restriction), $this->course);

        $this->assertStringContainsString('Met at least once', $rendered);
        $this->assertStringContainsString('Gate page', $rendered);
        $this->assertStringNotContainsString('<AVAILABILITY_', $rendered);
    }

    /**
     * Tests that a restriction on a course section is described with the activity it points at.
     */
    public function test_describe_restriction_of_section(): void {
        $info = new report_info_section(get_fast_modinfo($this->course)->get_section_info(1));

        $rendered = info::format_info($info->describe_restriction($this->restriction), $this->course);

        $this->assertStringContainsString('Met at least once', $rendered);
        $this->assertStringContainsString('Gate page', $rendered);
        $this->assertStringNotContainsString('<AVAILABILITY_', $rendered);
    }

    /**
     * Tests that the modinfo is only provided while the restriction is described, as core does it.
     */
    public function test_modinfo_is_withdrawn_after_description(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $info = new report_info_module(get_fast_modinfo($this->course)->get_cm($page->cmid));

        $info->describe_restriction($this->restriction);

        // Core only hands out the modinfo while it is set and throws otherwise, which is the only way to
        // tell from the outside that describe_restriction() has withdrawn it again.
        $this->expectException(\coding_exception::class);
        $info->get_modinfo();
    }
}
