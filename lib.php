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
 * Lib callbacks for CraftOffice Suite plugin.
 *
 * @package     local_craftoffice
 * @copyright   2026 Marcos Jean Sampaio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends primary navigation with the CraftOffice Suite link.
 *
 * @param global_navigation $navigation Global navigation object
 */
function local_craftoffice_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $url = new moodle_url('/local/craftoffice/index.php');
    $node = navigation_node::create(
        get_string('pluginname', 'local_craftoffice'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'craftoffice',
        new pix_icon('i/files', '')
    );

    $navigation->add_node($node);
}

/**
 * Serves CraftOffice document files from Moodle File Storage (mdl_files).
 * Supports public viewing for accesslevel = 0 without requiring user login.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context object
 * @param string $filearea File area name
 * @param array $args Extra parameters
 * @param bool $forcedownload Force download flag
 * @param array $options Additional options
 * @return bool False if file not found or access denied
 */
function local_craftoffice_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'documents') {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $doc = \local_craftoffice\officedoc::load($itemid);
    if (!$doc) {
        return false;
    }

    if (!\local_craftoffice\officedoc::can_view($doc)) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_craftoffice', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
