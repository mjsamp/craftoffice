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
 * CraftOffice Central Dashboard & Document Manager.
 *
 * @package     local_craftoffice
 * @copyright   2026 Marcos Jean Sampaio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

global $CFG, $PAGE, $USER, $DB, $OUTPUT;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/craftoffice/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_craftoffice') . ' - Dashboard');
$PAGE->set_heading(get_string('pluginname', 'local_craftoffice'));

// Process actions (delete, duplicate).
$action = optional_param('action', '', PARAM_ALPHA);
$docid = optional_param('id', 0, PARAM_INT);

if (!empty($action) && !empty($docid) && confirm_sesskey()) {
    $doc = \local_craftoffice\officedoc::load($docid);
    if ($doc) {
        if ($action === 'delete' && ($doc->usermodified == $USER->id || has_capability('local/craftoffice:manage', $context))) {
            \local_craftoffice\officedoc::delete($docid);
            redirect(new moodle_url('/local/craftoffice/index.php'), get_string('docdeleted', 'local_craftoffice'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'duplicate' && \local_craftoffice\officedoc::can_view($doc)) {
            $newid = \local_craftoffice\officedoc::duplicate($docid);
            redirect(new moodle_url('/local/craftoffice/editor.php', ['id' => $newid]), get_string('docduplicated', 'local_craftoffice'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

$dbman = $DB->get_manager();
$has_shares_table = $dbman->table_exists('local_craftoffice_shares');

// Auto-create shares table dynamically if missing
if (!$has_shares_table) {
    try {
        $table = new \xmldb_table('local_craftoffice_shares');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('docid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('docid', XMLDB_INDEX_NOTUNIQUE, ['docid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $dbman->create_table($table);
        $has_shares_table = true;
    } catch (\Exception $e) {
        $has_shares_table = false;
    }
}

// Fetch all documents accessible by current user including shared ones.
if ($has_shares_table) {
    $sql = "SELECT DISTINCT d.id, d.title, d.doctype, d.content, d.usermodified, d.accesslevel, d.menuname, d.timecreated, d.timemodified, u.firstname, u.lastname
              FROM {local_craftoffice_docs} d
              JOIN {user} u ON u.id = d.usermodified
         LEFT JOIN {local_craftoffice_shares} s ON s.docid = d.id
             WHERE d.accesslevel = 0
                OR d.accesslevel = 1
                OR (d.accesslevel = 2 AND d.usermodified = :userid1)
                OR (d.accesslevel = 3 AND (d.usermodified = :userid2 OR s.userid = :userid3))
          ORDER BY d.timemodified DESC";
    $docs = $DB->get_records_sql($sql, ['userid1' => $USER->id, 'userid2' => $USER->id, 'userid3' => $USER->id]);
} else {
    $sql = "SELECT d.id, d.title, d.doctype, d.content, d.usermodified, d.accesslevel, d.menuname, d.timecreated, d.timemodified, u.firstname, u.lastname
              FROM {local_craftoffice_docs} d
              JOIN {user} u ON u.id = d.usermodified
             WHERE d.accesslevel = 0
                OR d.accesslevel = 1
                OR (d.accesslevel = 2 AND d.usermodified = :userid)
          ORDER BY d.timemodified DESC";
    $docs = $DB->get_records_sql($sql, ['userid' => $USER->id]);
}

// Calculate document counts per category.
$count_all = count($docs);
$count_writer = 0;
$count_calc = 0;
$count_pdf = 0;
$count_deck = 0;
$count_private = 0;
$count_public = 0;
$count_shared = 0;

$fs = get_file_storage();
$formatted_docs = [];

foreach ($docs as $d) {
    $is_writer = ($d->doctype === 'text' || $d->doctype === 'writer' || $d->doctype === 'doc');
    $is_calc = ($d->doctype === 'spreadsheet' || $d->doctype === 'calc' || $d->doctype === 'excel');
    $is_pdf = ($d->doctype === 'pdf');
    $is_deck = ($d->doctype === 'presentation' || $d->doctype === 'deck' || $d->doctype === 'pptx');

    if ($is_writer) $count_writer++;
    else if ($is_calc) $count_calc++;
    else if ($is_pdf) $count_pdf++;
    else if ($is_deck) $count_deck++;

    if ($d->accesslevel == 2) $count_private++;
    if ($d->accesslevel == 0) $count_public++;
    if ($d->accesslevel == 3) $count_shared++;

    $s_files = $fs->get_area_files($context->id, 'local_craftoffice', 'documents', $d->id, 'timemodified DESC', false);
    $d_url = '';
    if (!empty($s_files)) {
        $f = reset($s_files);
        $d_url = moodle_url::make_pluginfile_url($context->id, 'local_craftoffice', 'documents', $d->id, '/', $f->get_filename())->out(false);
    }

    $author_fullname = fullname($d);
    $access_text = $d->accesslevel == 0 ? get_string('accesspublic', 'local_craftoffice') : ($d->accesslevel == 1 ? get_string('accessauthenticated', 'local_craftoffice') : ($d->accesslevel == 3 ? get_string('accessshared', 'local_craftoffice') : get_string('accessprivate', 'local_craftoffice')));

    $formatted_docs[] = [
        'id' => (int)$d->id,
        'title' => $d->title,
        'lower_title' => s(mb_strtolower($d->title)),
        'lower_author' => s(mb_strtolower($author_fullname)),
        'accesslevel' => (int)$d->accesslevel,
        'access_label' => $access_text,
        'norm_type' => $is_writer ? 'text' : ($is_calc ? 'spreadsheet' : ($is_pdf ? 'pdf' : 'presentation')),
        'icon_class' => $is_writer ? 'writer' : ($is_calc ? 'calc' : ($is_pdf ? 'pdf' : 'deck')),
        'icon_symbol' => $is_writer ? 'W' : ($is_calc ? 'C' : ($is_pdf ? 'PDF' : 'D')),
        'type_label' => $is_writer ? 'WordCraft' : ($is_calc ? 'GridCraft' : ($is_pdf ? 'PdfCraft' : 'DeckCraft')),
        'author_name' => $author_fullname,
        'modified_date' => userdate($d->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'editor_url' => (new moodle_url('/local/craftoffice/editor.php', ['id' => $d->id]))->out(false),
        'edit_url' => (new moodle_url('/local/craftoffice/edit.php', ['id' => $d->id]))->out(false),
        'duplicate_url' => (new moodle_url('/local/craftoffice/index.php', ['action' => 'duplicate', 'id' => $d->id, 'sesskey' => sesskey()]))->out(false),
        'delete_url' => (new moodle_url('/local/craftoffice/index.php', ['action' => 'delete', 'id' => $d->id, 'sesskey' => sesskey()]))->out(false),
        'can_delete' => ($d->usermodified == $USER->id || has_capability('local/craftoffice:manage', $context)),
        'doc_file_url' => $d_url,
    ];
}

$template_data = [
    'new_writer_url' => (new moodle_url('/local/craftoffice/edit.php', ['doctype' => 'text']))->out(false),
    'new_calc_url' => (new moodle_url('/local/craftoffice/edit.php', ['doctype' => 'spreadsheet']))->out(false),
    'new_pdf_url' => (new moodle_url('/local/craftoffice/edit.php', ['doctype' => 'pdf']))->out(false),
    'new_deck_url' => (new moodle_url('/local/craftoffice/edit.php', ['doctype' => 'presentation']))->out(false),
    'count_all' => $count_all,
    'count_writer' => $count_writer,
    'count_calc' => $count_calc,
    'count_pdf' => $count_pdf,
    'count_deck' => $count_deck,
    'count_public' => $count_public,
    'count_private' => $count_private,
    'count_shared' => $count_shared,
    'has_docs' => !empty($formatted_docs),
    'docs' => $formatted_docs,
];

$PAGE->requires->css('/local/craftoffice/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_craftoffice/dashboard', $template_data);
?>
<script>
var activeCategoryFilter = 'all';

function filterCategory(category, element) {
    activeCategoryFilter = category;

    var links = document.querySelectorAll('.craftoffice-nav-link');
    links.forEach(function(l) { l.classList.remove('active'); });
    if (element) element.classList.add('active');

    searchDocuments();
}

function searchDocuments() {
    var query = document.getElementById('craftoffice-search').value.toLowerCase().trim();
    var items = document.querySelectorAll('.doc-item');

    items.forEach(function(item) {
        var docType = item.getAttribute('data-type');
        var accessLevel = item.getAttribute('data-access');
        var title = item.getAttribute('data-title') || '';
        var author = item.getAttribute('data-author') || '';

        var matchesCategory = false;
        if (activeCategoryFilter === 'all') {
            matchesCategory = true;
        } else if (activeCategoryFilter === 'access_public') {
            matchesCategory = (accessLevel === '0');
        } else if (activeCategoryFilter === 'access_private') {
            matchesCategory = (accessLevel === '2');
        } else if (activeCategoryFilter === 'access_shared') {
            matchesCategory = (accessLevel === '3');
        } else {
            matchesCategory = (docType === activeCategoryFilter);
        }

        var matchesQuery = (query === '' || title.indexOf(query) !== -1 || author.indexOf(query) !== -1);

        if (matchesCategory && matchesQuery) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function switchViewMode(mode) {
    var gridContainer = document.getElementById('craftoffice-view-grid-container');
    var listContainer = document.getElementById('craftoffice-view-list-container');
    var btnGrid = document.getElementById('btn-view-grid');
    var btnList = document.getElementById('btn-view-list');

    if (mode === 'list') {
        if (gridContainer) gridContainer.style.display = 'none';
        if (listContainer) listContainer.style.display = 'block';
        if (btnGrid) btnGrid.classList.remove('active');
        if (btnList) btnList.classList.add('active');
        localStorage.setItem('craftoffice_view_mode', 'list');
    } else {
        if (gridContainer) gridContainer.style.display = 'grid';
        if (listContainer) listContainer.style.display = 'none';
        if (btnGrid) btnGrid.classList.add('active');
        if (btnList) btnList.classList.remove('active');
        localStorage.setItem('craftoffice_view_mode', 'grid');
    }
}

// Restore saved view mode preference
document.addEventListener('DOMContentLoaded', function() {
    var savedMode = localStorage.getItem('craftoffice_view_mode') || 'grid';
    switchViewMode(savedMode);
});
</script>
<?php
echo $OUTPUT->footer();
