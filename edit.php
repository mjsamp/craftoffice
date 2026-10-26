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
 * Edit / Create document settings in CraftOffice.
 *
 * @package     local_craftoffice
 * @copyright   2026 Marcos Jean Sampaio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

global $CFG, $PAGE, $USER, $DB, $OUTPUT;

require_login();

$id = optional_param('id', 0, PARAM_INT);
$doctype = optional_param('doctype', 'text', PARAM_ALPHA);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/craftoffice/edit.php', ['id' => $id, 'doctype' => $doctype]));

if ($id) {
    $doc = \local_craftoffice\officedoc::load($id);
    if (!$doc) print_error('invalidrecord', 'error');
    if (!\local_craftoffice\officedoc::can_view($doc)) print_error('accessdenied', 'admin');
    $PAGE->set_title(get_string('editdoc', 'local_craftoffice') . ': ' . $doc->title);
    $doc->sharedusers = \local_craftoffice\officedoc::get_shared_users($id);
} else {
    $doc = new stdClass();
    $doc->id = 0;
    $doc->title = '';
    $doc->doctype = $doctype;
    $doc->accesslevel = 1;
    $doc->sharedusers = [];
    $PAGE->set_title(get_string('createdoc', 'local_craftoffice'));
}

/**
 * Class craftoffice_edit_form
 */
class craftoffice_edit_form extends moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('doctitle', 'local_craftoffice'), array('size' => '64'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $options = array(
            'text' => 'WordCraft Document (.docx)',
            'spreadsheet' => 'GridCraft Spreadsheet (.xlsx)',
            'pdf' => 'PdfCraft Document (.pdf)',
            'presentation' => 'DeckCraft Presentation (.pptx)',
        );
        $mform->addElement('select', 'doctype', get_string('doctype', 'local_craftoffice'), $options);
        $mform->setType('doctype', PARAM_ALPHA);

        $accessoptions = array(
            0 => get_string('accesspublic', 'local_craftoffice'),
            1 => get_string('accessauthenticated', 'local_craftoffice'),
            2 => get_string('accessprivate', 'local_craftoffice'),
            3 => get_string('accessshared', 'local_craftoffice'),
        );
        $mform->addElement('select', 'accesslevel', get_string('accesslevel', 'local_craftoffice'), $accessoptions);
        $mform->setType('accesslevel', PARAM_INT);

        // Specific Moodle Users Selector for Shared Access Level
        $users_sql = "SELECT id, " . $DB->sql_fullname() . " AS fullname FROM {user} WHERE deleted = 0 AND suspended = 0 AND id > 1 ORDER BY firstname ASC";
        $users_records = $DB->get_records_sql($users_sql);
        $user_options = [];
        foreach ($users_records as $u) {
            $user_options[$u->id] = $u->fullname;
        }

        $mform->addElement('autocomplete', 'sharedusers', get_string('sharewithusers', 'local_craftoffice'), $user_options, array(
            'multiple' => true,
            'placeholder' => get_string('selectusers', 'local_craftoffice')
        ));

        // Optional File Upload Field for Moodle File Storage API
        $mform->addElement('filepicker', 'docfile', get_string('uploadinitialfile', 'local_craftoffice'), null, array(
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => array('.docx', '.xlsx', '.pdf', '.pptx', '.deckcraft', '.ods', '.odt', '.csv')
        ));

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

$mform = new craftoffice_edit_form(null, array('id' => $id));
$mform->set_data($doc);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/craftoffice/index.php'));
} else if ($data = $mform->get_data()) {
    if (!empty($data->id)) {
        \local_craftoffice\officedoc::update($data);
        $docid = $data->id;
    } else {
        $docid = \local_craftoffice\officedoc::create($data);
    }

    // Save shared user IDs if access level is Shared (3)
    $shared_user_ids = !empty($data->sharedusers) ? (array)$data->sharedusers : [];
    \local_craftoffice\officedoc::save_shared_users($docid, $shared_user_ids);

    // Save uploaded initial file into Moodle File Storage API (mdl_files) if provided.
    $filecontent = $mform->get_file_content('docfile');
    $filename = $mform->get_new_filename('docfile');
    if (!empty($filecontent) && !empty($filename)) {
        \local_craftoffice\officedoc::save_file($docid, $filename, $filecontent);
    }

    redirect(new moodle_url('/local/craftoffice/editor.php', ['id' => $docid]));
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
