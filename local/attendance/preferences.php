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
 * Manage attendance settings
 *
 * @package    local_attendance
 * @copyright  2023 Dipanshu Kasera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new local_attendance_preferences_page_params();
$systemcontext = context_system::instance();

$id                         = required_param('id', PARAM_INT);
$pageparams->action         = optional_param('action', null, PARAM_INT);
$pageparams->statusid       = optional_param('statusid', null, PARAM_INT);
$pageparams->statusset      = optional_param('statusset', 0, PARAM_INT); // Set of statuses to view.

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/attendance:changepreferences', $context);

// Make sure the statusset is valid.
$maxstatusset = local_attendance_get_max_statusset($att->id);
if ($pageparams->statusset > $maxstatusset + 1) {
    $pageparams->statusset = $maxstatusset + 1;
}

$att = new local_attendance_structure($att, $cm, $course, $context, $pageparams);

$PAGE->set_url($att->url_preferences());
$PAGE->set_title($course->shortname. ": ".$att->name.' - '.get_string('settings', 'attendance'));
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(false);
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('settings', 'attendance'));
$PAGE->navbar->ignore_active(true);
$PAGE->set_secondary_navigation(false);

$errors = array();

// Check sesskey if we are performing an action.
if (!empty($att->pageparams->action)) {
    require_sesskey();
}
$notification = '';
// TODO: combine this with the stuff in defaultstatus.php to avoid code duplication.
switch ($att->pageparams->action) {
    case local_attendance_preferences_page_params::ACTION_ADD:
        $newacronym         = optional_param('newacronym', null, PARAM_TEXT);
        $newdescription     = optional_param('newdescription', null, PARAM_TEXT);
        $newgrade           = optional_param('newgrade', 0, PARAM_RAW);
        $newstudentavailability = optional_param('newstudentavailability', null, PARAM_INT);
        $newgrade = empty($newgrade) ? 0 : unformat_float($newgrade);

        $newstatus = new stdClass();
        $newstatus->attendanceid = $att->id;
        $newstatus->acronym = $newacronym;
        $newstatus->description = $newdescription;
        $newstatus->grade = $newgrade;
        $newstatus->studentavailability = $newstudentavailability;
        $newstatus->setnumber = $att->pageparams->statusset;
        $newstatus->cm = $att->cm;
        $newstatus->context = $att->context;

        $status = local_attendance_add_status($newstatus);
        if (!$status) {
            $notification = $OUTPUT->notification(get_string('cantaddstatus', 'attendance'), 'error');
        }

        if ($pageparams->statusset > $maxstatusset) {
            $maxstatusset = $pageparams->statusset; // Make sure the new maximum is shown without a page refresh.
        }
        break;
    case local_attendance_preferences_page_params::ACTION_DELETE:
        if (local_attendance_has_logs_for_status($att->pageparams->statusid)) {
            throw new moodle_exception('cantdeletestatus', 'attendance', "attsettings.php?id=$id");
        }

        $confirm    = optional_param('confirm', null, PARAM_INT);
        $statuses = $att->get_statuses(false);
        $status = $statuses[$att->pageparams->statusid];

        if (isset($confirm)) {
            local_attendance_remove_status($status);
            redirect($att->url_preferences(), get_string('statusdeleted', 'attendance'));
        }

        $message = get_string('deletecheckfull', 'attendance', get_string('variable', 'attendance'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= $status->acronym.': '.
                    ($status->description ? $status->description : get_string('nodescription', 'attendance'));
        $params = array_merge($att->pageparams->get_significant_params(), array('confirm' => 1));
        echo $OUTPUT->header();
        echo $OUTPUT->confirm($message, $att->url_preferences($params), $att->url_preferences());
        echo "<script>
                    document.getElementsByClassName('btn-primary')[0].innerHTML = 'Delete';
            </script>";
        echo $OUTPUT->footer();
        exit;
    case local_attendance_preferences_page_params::ACTION_HIDE:
        $statuses = $att->get_statuses(false);
        $status = $statuses[$att->pageparams->statusid];
        local_attendance_update_status($status, null, null, null, 0, $att->context, $att->cm);
        break;
    case local_attendance_preferences_page_params::ACTION_SHOW:
        $statuses = $att->get_statuses(false);
        $status = $statuses[$att->pageparams->statusid];
        local_attendance_update_status($status, null, null, null, 1, $att->context, $att->cm);
        break;
    case local_attendance_preferences_page_params::ACTION_SAVE:
        $acronym        = required_param_array('acronym', PARAM_TEXT);
        $description    = required_param_array('description', PARAM_TEXT);
        $grade          = required_param_array('grade', PARAM_RAW);
        $studentavailability = optional_param_array('studentavailability', null, PARAM_RAW);
        $unmarkedstatus = optional_param('setunmarked', null, PARAM_INT);

        foreach ($grade as &$val) {
            $val = unformat_float($val);
        }
        $statuses = $att->get_statuses(false);

        foreach ($acronym as $id => $v) {
            $status = $statuses[$id];
            $setunmarked = false;
            if ($unmarkedstatus == $id) {
                $setunmarked = true;
            }
            $errors[$id] = local_attendance_update_status($status, $acronym[$id], $description[$id], $grade[$id],
                                                    null, $att->context, $att->cm, $studentavailability[$id], $setunmarked);
        }
        local_attendance_update_users_grade($att);
        break;
}

$output = $PAGE->get_renderer('local_attendance');
$tabs = new local_attendance\output\tabs($att, local_attendance\output\tabs::TAB_PREFERENCES);
$prefdata = new local_attendance\output\preferences_data($att, array_filter($errors));
$setselector = new local_attendance\output\set_selector($att, $maxstatusset);

// Output starts here.

echo $output->header();
if (!empty($notification)) {
    echo $notification;
}

if (is_siteadmin()
    || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)
    || has_capability('local/costcenter:manage_ownorganization', $systemcontext)
    || has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
    $semesterid = $DB->get_field('local_program_level_courses', 'levelid', ['courseid' => $course->id]);
} else {
    $semesterid = 0;
}
echo html_writer::link(new moodle_url('/local/timetable/individual_session.php?tlid='.$semesterid),''.get_string('back', 'local_timetable').'',array('id'=>'local_timetable_batchwisebu', 'class' => 'btn btn-primary'));

echo $output->render($tabs);

echo $OUTPUT->box(get_string('preferences_desc', 'attendance'), 'generalbox attendancedesc', 'notice');
echo $output->render($setselector);
echo $output->render($prefdata);

echo $output->footer();
