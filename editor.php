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
 * Direct PHP Editor Launcher for CraftOffice Suite (No Iframe).
 * Connects WordCraft, GridCraft, PdfCraft, and DeckCraft directly with Moodle File Storage API (mdl_files).
 * Supports Public View (read-only), live presence heartbeats, new version alerts, and permission checks on save.
 *
 * @package     local_craftoffice
 * @copyright   2026 Marcos Jean Sampaio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

global $CFG, $PAGE, $USER, $DB, $OUTPUT;

$id = required_param('id', PARAM_INT);
$doc = \local_craftoffice\officedoc::load($id);

if (!$doc) {
    print_error('invalidrecord', 'error');
}

// Check if user has permission to view.
if (!\local_craftoffice\officedoc::can_view($doc)) {
    require_login();
    if (!\local_craftoffice\officedoc::can_view($doc)) {
        print_error('accessdenied', 'admin');
    }
}

$can_edit = \local_craftoffice\officedoc::can_edit($doc);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded'); // Embedded layout prevents Bootstrap popover/tooltip conflicts in Moodle themes.
$PAGE->set_url(new moodle_url('/local/craftoffice/editor.php', ['id' => $id]));
$PAGE->set_title($doc->title . ' - CraftOffice');
$PAGE->set_heading($doc->title);

// Process AJAX requests (Save, File Uploads, Presence Heartbeats).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = optional_param('action', '', PARAM_ALPHA);

    // 1. Process Presence Heartbeat Ping & Check for New Document Versions.
    if ($action === 'heartbeat') {
        $editors = \local_craftoffice\officedoc::heartbeat($doc->id);

        $latest_doc = \local_craftoffice\officedoc::load($doc->id);
        $last_user_name = '';
        $last_user_id = 0;
        if ($latest_doc && $latest_doc->usermodified) {
            $last_user_id = (int)$latest_doc->usermodified;
            $last_user = $DB->get_record('user', ['id' => $latest_doc->usermodified]);
            if ($last_user) {
                $last_user_name = fullname($last_user);
            }
        }

        echo json_encode([
            'status' => 'success',
            'editors' => $editors,
            'doc_timemodified' => (int)($latest_doc->timemodified ?? 0),
            'last_saved_by' => $last_user_name,
            'last_saved_by_id' => $last_user_id,
            'last_saved_time' => userdate($latest_doc->timemodified ?? 0, get_string('strftimedatetimeshort', 'langconfig'))
        ]);
        exit;
    }

    // Enforce Edit Permissions on server for file saves.
    if (!$can_edit) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => get_string('nopermissionsave', 'local_craftoffice')
        ]);
        exit;
    }

    // 2. Process Raw File Upload directly into Moodle File Storage API (mdl_files).
    if (!empty($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $filename = clean_filename($_FILES['doc_file']['name']);
        $tempfile = $_FILES['doc_file']['tmp_name'];

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_craftoffice', 'documents', $doc->id);

        $file_record = array(
            'contextid'   => $context->id,
            'component'   => 'local_craftoffice',
            'filearea'    => 'documents',
            'itemid'      => $doc->id,
            'filepath'    => '/',
            'filename'    => $filename,
            'timecreated' => time(),
            'timemodified'=> time(),
        );
        $stored_file = $fs->create_file_from_pathname($file_record, $tempfile);

        $fileurl = moodle_url::make_pluginfile_url($context->id, 'local_craftoffice', 'documents', $doc->id, '/', $filename)->out(false) . '?t=' . time();

        $update = new \stdClass();
        $update->id = $doc->id;
        $update->content = json_encode(['filename' => $filename, 'fileurl' => $fileurl]);
        \local_craftoffice\officedoc::update($update);

        echo json_encode(['status' => 'success', 'fileurl' => $fileurl, 'filename' => $filename]);
        exit;
    }

    // 3. Process Content / Base64 Document Bytes Save directly into Moodle File Storage API (mdl_files).
    $content = optional_param('content', '', PARAM_RAW);
    $filename = optional_param('filename', 'document.docx', PARAM_FILE);

    if ($content !== null && $content !== '') {
        $binary = $content;
        if (preg_match('/^data:.*?;base64,/', $content)) {
            $binary = base64_decode(preg_replace('/^data:.*?;base64,/', '', $content));
        } else {
            $decoded = @base64_decode($content, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $binary = $decoded;
            }
        }

        if ($binary !== false && strlen($binary) > 0) {
            \local_craftoffice\officedoc::save_file($doc->id, $filename, $binary);
        }

        $update = new \stdClass();
        $update->id = $doc->id;
        $update->timemodified = time();
        \local_craftoffice\officedoc::update($update);

        echo json_encode(['status' => 'success', 'timemodified' => time()]);
        exit;
    }
}

// Determine active engine type and correct Canvas Element ID.
$is_writer = ($doc->doctype === 'text' || $doc->doctype === 'writer' || $doc->doctype === 'doc');
$is_calc = ($doc->doctype === 'spreadsheet' || $doc->doctype === 'calc' || $doc->doctype === 'excel');
$is_pdf = ($doc->doctype === 'pdf');
$is_deck = ($doc->doctype === 'presentation' || $doc->doctype === 'deck' || $doc->doctype === 'pptx');

if (!$is_writer && !$is_calc && !$is_pdf && !$is_deck) {
    $is_writer = true;
}

// Each WASM app looks for its specific canvas ID.
$canvas_id = $is_writer ? 'wordcraft_canvas' : ($is_calc ? 'gridcraft_canvas' : ($is_pdf ? 'pdfcraft' : 'deckcraft_canvas'));
$app_label = $is_writer ? 'WordCraft' : ($is_calc ? 'GridCraft' : ($is_pdf ? 'PdfCraft' : 'DeckCraft'));

// Check for stored document file in Moodle File Storage API (mdl_files).
$fs = get_file_storage();
$stored_files = $fs->get_area_files($context->id, 'local_craftoffice', 'documents', $doc->id, 'timemodified DESC', false);
$stored_file_url = '';
if (!empty($stored_files)) {
    $file = reset($stored_files);
    $stored_file_url = moodle_url::make_pluginfile_url($context->id, 'local_craftoffice', 'documents', $doc->id, '/', $file->get_filename())->out(false) . '?t=' . $file->get_timemodified();
}

$template_data = [
    'title' => s($doc->title),
    'app_label' => $app_label,
    'canvas_id' => $canvas_id,
    'can_edit' => $can_edit,
    'stored_file_url' => $stored_file_url,
    'back_url' => (new moodle_url('/local/craftoffice/index.php'))->out(false),
];

$PAGE->requires->css('/local/craftoffice/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_craftoffice/editor', $template_data);
?>

<script>
window._craftOfficeDocId = <?php echo (int)$doc->id; ?>;
window._craftOfficeDocType = <?php echo json_encode($doc->doctype); ?>;
window._craftOfficeFileUrl = <?php echo json_encode($stored_file_url); ?>;
window._craftOfficeCanEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
window._craftOfficeLoadedTime = <?php echo (int)($doc->timemodified ?? 0); ?>;
window._craftOfficeUserId = <?php echo (int)($USER->id ?? 0); ?>;

window._strSavedSuccess = <?php echo json_encode(get_string('savedsuccess', 'local_craftoffice')); ?>;
window._strNoPermissionSave = <?php echo json_encode(get_string('nopermissionsave', 'local_craftoffice')); ?>;
window._strNoPermissionStatus = <?php echo json_encode(get_string('nopermissionstatus', 'local_craftoffice')); ?>;

window.addEventListener('craftoffice:saved', function() {
    var statusEl = document.getElementById('craftoffice-status');
    if (!window._craftOfficeCanEdit) {
        alert(window._strNoPermissionSave);
        if (statusEl) {
            statusEl.style.color = '#dc3545';
            statusEl.textContent = window._strNoPermissionStatus;
        }
        return;
    }
    if (statusEl) {
        statusEl.style.color = '#28a745';
        statusEl.textContent = window._strSavedSuccess;
        setTimeout(function() { statusEl.textContent = ''; }, 3000);
    }
});

// Active Presence Heartbeat & Live Editor Count + New Version Alerts
function pingHeartbeat() {
    var formData = new FormData();
    formData.append('id', window._craftOfficeDocId);
    formData.append('action', 'heartbeat');

    fetch('<?php echo new moodle_url('/local/craftoffice/editor.php', ['id' => $doc->id]); ?>', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            if (data.editors) {
                updatePresenceBadge(data.editors);
            }
            if (data.doc_timemodified && data.doc_timemodified > window._craftOfficeLoadedTime && data.last_saved_by_id !== window._craftOfficeUserId) {
                showNewVersionBanner(data.last_saved_by, data.last_saved_time);
            }
        }
    })
    .catch(function(err) {});
}

function updatePresenceBadge(editors) {
    var badgeEl = document.getElementById('craftoffice-presence-badge');
    if (!badgeEl) return;

    var otherEditors = editors.filter(function(e) { return !e.is_me; });
    var mySessions = editors.filter(function(e) { return e.is_me; });

    if (otherEditors.length === 0) {
        badgeEl.className = 'badge badge-success';
        badgeEl.style.backgroundColor = '#28a745';
        badgeEl.style.color = '#fff';

        if (mySessions.length > 1) {
            badgeEl.innerHTML = '<i class="fa fa-circle"></i> Editando (Você - ' + mySessions.length + ' abas)';
            badgeEl.title = 'Você possui ' + mySessions.length + ' abas/sessões deste documento abertas.';
        } else {
            badgeEl.innerHTML = '<i class="fa fa-circle"></i> Editando (Você)';
            badgeEl.title = 'Apenas você está editando este documento no momento.';
        }
    } else {
        var otherNames = [];
        otherEditors.forEach(function(e) {
            if (otherNames.indexOf(e.fullname) === -1) {
                otherNames.push(e.fullname);
            }
        });

        var count = otherNames.length;
        var namesText = otherNames.join(', ');

        badgeEl.className = 'badge badge-warning';
        badgeEl.style.backgroundColor = '#fd7e14';
        badgeEl.style.color = '#fff';
        badgeEl.innerHTML = '<i class="fa fa-users"></i> Editando por você e mais ' + count + (count === 1 ? ' pessoa' : ' pessoas');
        badgeEl.title = 'Usuários ativos no momento: ' + namesText;
    }
}

function showNewVersionBanner(authorName, timeText) {
    var bannerEl = document.getElementById('craftoffice-new-version-banner');
    if (!bannerEl) {
        bannerEl = document.createElement('div');
        bannerEl.id = 'craftoffice-new-version-banner';
        bannerEl.style.cssText = 'position: absolute; top: 55px; left: 50%; transform: translateX(-50%); z-index: 9999; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 10px 20px; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 15px; font-family: sans-serif; font-size: 14px;';
        document.body.appendChild(bannerEl);
    }

    bannerEl.innerHTML = '<span><i class="fa fa-bell" style="color: #e67e22;"></i> <strong>' + authorName + '</strong> salvou uma nova versão deste documento no Moodle (' + timeText + ').</span>' +
        '<button onclick="window.location.reload();" class="btn btn-sm btn-primary" style="font-weight: bold;"><i class="fa fa-refresh"></i> Recarregar para Ver</button>';
}

// Send initial presence ping and repeat every 10 seconds
pingHeartbeat();
setInterval(pingHeartbeat, 10000);
</script>

<script type="module">
// Dynamically import ES Module WASM engine (WordCraft, GridCraft, PdfCraft, or DeckCraft)
var docType = window._craftOfficeDocType || 'text';
var wasmName = 'wordcraft-web';

if (docType === 'spreadsheet' || docType === 'calc' || docType === 'excel') {
    wasmName = 'gridcraft-web';
} else if (docType === 'pdf') {
    wasmName = 'pdfcraft-web';
} else if (docType === 'presentation' || docType === 'deck' || docType === 'pptx') {
    wasmName = 'deckcraft-web';
}

var jsUrl = '<?php echo $CFG->wwwroot; ?>/local/craftoffice/editor/wasm/' + wasmName + '.js';
var wasmUrl = '<?php echo $CFG->wwwroot; ?>/local/craftoffice/editor/wasm/' + wasmName + '_bg.wasm';

import(jsUrl).then(function(module) {
    var initFn = module.default || window.wasm_bindgen;
    return initFn({ module_or_path: realmPath(wasmUrl) });
}).then(function() {
    var loading = document.getElementById('wordcraft_loading');
    if (loading) loading.style.display = 'none';
    console.log('CraftOffice WASM engine (' + wasmName + ') loaded successfully!');
}).catch(function(err) {
    console.error('Erro ao inicializar o WASM no Moodle:', err);
});

function realmPath(url) {
    return url;
}
</script>

<script>
function uploadDirectFile(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];

    var statusEl = document.getElementById('craftoffice-status');
    if (statusEl) statusEl.textContent = <?php echo json_encode(get_string('uploadingfile', 'local_craftoffice')); ?>;

    var formData = new FormData();
    formData.append('id', window._craftOfficeDocId);
    formData.append('doc_file', file);

    fetch('<?php echo new moodle_url('/local/craftoffice/editor.php', ['id' => $doc->id]); ?>', {
        method: 'POST',
        body: formData
    })
    .then(function(res) {
        if (!res.ok) {
            return res.json().then(function(data) { throw new Error(data.message || window._strNoPermissionStatus); });
        }
        return res.json();
    })
    .then(function(data) {
        if (data.status === 'success') {
            if (statusEl) statusEl.textContent = <?php echo json_encode(get_string('uploadedsuccess', 'local_craftoffice')); ?>;
            window.location.reload();
        }
    })
    .catch(function(err) {
        alert(err.message || <?php echo json_encode(get_string('uploaderror', 'local_craftoffice')); ?>);
        if (statusEl) statusEl.textContent = <?php echo json_encode(get_string('uploaderror', 'local_craftoffice')); ?>;
    });
}
</script>

<?php
echo $OUTPUT->footer();
