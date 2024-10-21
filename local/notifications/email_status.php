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
 * @package
 * @author     eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot. "/local/notifications/classes/local/email_status_func.php");
global $CFG, $USER, $PAGE, $OUTPUT;
$id = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$sitecontext = context_system::instance();
require_login();
$PAGE->set_url('/local/notifications/email_status.php', array());
$PAGE->set_context($sitecontext);
$PAGE->set_title(get_string('email_status', 'local_notifications'));
$PAGE->set_heading(get_string('email_status', 'local_notifications'));

$PAGE->navbar->add(get_string('notification_link','local_notifications'), new moodle_url("/local/notifications/index.php"));
$PAGE->navbar->add(get_string('email_status','local_notifications'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/local/notifications/css/jquery.dataTables.min.css', true);
$PAGE->requires->js('/local/notifications/js/jquery.dataTables.min.js', true);
$renderer = $PAGE->get_renderer('local_notifications');
echo $OUTPUT->header();
echo $renderer->view_email_status();
echo $OUTPUT->footer();
