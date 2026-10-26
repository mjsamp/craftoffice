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
 * CraftOffice Document Management Class.
 * Integrates directly with Moodle File Storage API (mdl_files).
 *
 * @package     local_craftoffice
 * @copyright   2026 Marcos Jean Sampaio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_craftoffice;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use context_system;

/**
 * Class officedoc
 */
class officedoc {

    /** @var stdClass Document record data */
    private $_data;

    /**
     * Constructor.
     * @param stdClass $data
     */
    public function __construct(stdClass $data) {
        $this->_data = $data;
    }

    /**
     * Creates a new document record and seeds an initial file in Moodle File Storage API (mdl_files).
     * @param stdClass $data Document creation data
     * @return int New ID
     */
    public static function create(stdClass $data): int {
        global $DB, $USER;

        $now = time();
        $record = new stdClass();
        $record->title = !empty($data->title) ? trim($data->title) : 'Novo Documento';
        $record->doctype = !empty($data->doctype) ? $data->doctype : 'text';
        $record->content = isset($data->content) ? $data->content : '';
        $record->usermodified = !empty($data->usermodified) ? $data->usermodified : ($USER->id ?? 0);
        $record->accesslevel = isset($data->accesslevel) ? (int)$data->accesslevel : 1;
        $record->menuname = !empty($data->menuname) ? trim($data->menuname) : '';
        $record->timecreated = $now;
        $record->timemodified = $now;

        $docid = $DB->insert_record('local_craftoffice_docs', $record);

        // Seed initial file into Moodle File Storage API (mdl_files) if not already created
        $filename = self::get_default_filename($record->doctype);
        $initial_content = self::get_default_file_content($record->doctype);
        self::save_file($docid, $filename, $initial_content);

        return $docid;
    }

    /**
     * Updates an existing document record.
     * @param stdClass $data Update data
     * @return bool True on success
     */
    public static function update(stdClass $data): bool {
        global $DB, $USER;

        if (empty($data->id)) return false;

        $record = $DB->get_record('local_craftoffice_docs', ['id' => $data->id]);
        if (!$record) return false;

        if (isset($data->title)) $record->title = trim($data->title);
        if (isset($data->doctype)) $record->doctype = $data->doctype;
        if (isset($data->content)) $record->content = $data->content;
        if (isset($data->accesslevel)) $record->accesslevel = (int)$data->accesslevel;
        if (isset($data->menuname)) $record->menuname = trim($data->menuname);

        $record->usermodified = $USER->id ?? $record->usermodified;
        $record->timemodified = time();

        return $DB->update_record('local_craftoffice_docs', $record);
    }

    /**
     * Loads a document by ID.
     * @param int $id Document ID
     * @return stdClass|false Document record
     */
    public static function load(int $id) {
        global $DB;
        return $DB->get_record('local_craftoffice_docs', ['id' => $id]);
    }

    /**
     * Deletes a document and its stored files in Moodle File Storage API (mdl_files).
     * @param int $id Document ID
     * @return bool True on success
     */
    public static function delete(int $id): bool {
        global $DB;

        $context = context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_craftoffice', 'documents', $id);
        $DB->delete_records('local_craftoffice_shares', ['docid' => $id]);

        return $DB->delete_records('local_craftoffice_docs', ['id' => $id]);
    }

    /**
     * Duplicates a document and its stored files in mdl_files.
     * @param int $id Document ID
     * @return int New document ID
     */
    public static function duplicate(int $id): int {
        global $DB, $USER;

        $doc = self::load($id);
        if (!$doc) return 0;

        $clone = clone $doc;
        unset($clone->id);
        $clone->title = $doc->title . ' (Cópia)';
        $clone->usermodified = $USER->id ?? 0;
        $clone->timecreated = time();
        $clone->timemodified = time();

        $newid = $DB->insert_record('local_craftoffice_docs', $clone);

        // Copy stored files in Moodle File Storage (mdl_files)
        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_craftoffice', 'documents', $id, 'id', false);
        foreach ($files as $file) {
            $filerecord = array(
                'contextid' => $context->id,
                'component' => 'local_craftoffice',
                'filearea' => 'documents',
                'itemid' => $newid,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            );
            $fs->create_file_from_storedfile($filerecord, $file);
        }

        // Duplicate shared users list if any
        $shared_users = self::get_shared_users($id);
        if (!empty($shared_users)) {
            self::save_shared_users($newid, $shared_users);
        }

        return $newid;
    }

    /**
     * Saves file bytes directly into Moodle File Storage API (mdl_files).
     * @param int $docid Document ID
     * @param string $filename Target filename
     * @param string $content Binary file content
     * @return \stored_file Stored file object
     */
    public static function save_file(int $docid, string $filename, string $content) {
        $context = context_system::instance();
        $fs = get_file_storage();

        // Delete existing file in this area/itemid
        $fs->delete_area_files($context->id, 'local_craftoffice', 'documents', $docid);

        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'local_craftoffice',
            'filearea' => 'documents',
            'itemid' => $docid,
            'filepath' => '/',
            'filename' => $filename,
            'timecreated' => time(),
            'timemodified' => time(),
        );

        return $fs->create_file_from_string($filerecord, $content);
    }

    /**
     * Records a presence heartbeat for the current user editing document $docid.
     * Cleans up expired sessions (> 25s) and returns all active editors.
     *
     * @param int $docid Document ID
     * @return array Array of active editor info objects
     */
    public static function heartbeat(int $docid): array {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        $now = time();
        $cutoff = $now - 25;
        $sesskey = sesskey() ?? session_id();

        $dbman = $DB->get_manager();

        // Auto-create table dynamically on the fly if missing on existing Moodle upgrades
        if (!$dbman->table_exists('local_craftoffice_locks')) {
            try {
                $table = new \xmldb_table('local_craftoffice_locks');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
                $table->add_field('docid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
                $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
                $table->add_field('sesskey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_index('docid', XMLDB_INDEX_NOTUNIQUE, ['docid']);
                $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
                $table->add_index('sesskey', XMLDB_INDEX_NOTUNIQUE, ['sesskey']);
                $dbman->create_table($table);
            } catch (\Exception $e) {
                // Ignore if concurrently created
            }
        }

        try {
            // Clean expired heartbeats
            $DB->delete_records_select('local_craftoffice_locks', 'timemodified < :cutoff', ['cutoff' => $cutoff]);

            // Insert or update heartbeat for this active session
            $lock = $DB->get_record('local_craftoffice_locks', ['docid' => $docid, 'userid' => $USER->id, 'sesskey' => $sesskey]);
            if ($lock) {
                $lock->timemodified = $now;
                $DB->update_record('local_craftoffice_locks', $lock);
            } else {
                $lock = new stdClass();
                $lock->docid = $docid;
                $lock->userid = $USER->id;
                $lock->sesskey = $sesskey;
                $lock->timecreated = $now;
                $lock->timemodified = $now;
                $DB->insert_record('local_craftoffice_locks', $lock);
            }

            // Fetch all active sessions for this document
            $sql = "SELECT l.id, l.sesskey, u.id as userid, u.firstname, u.lastname
                      FROM {local_craftoffice_locks} l
                      JOIN {user} u ON u.id = l.userid
                     WHERE l.docid = :docid
                       AND l.timemodified >= :cutoff";

            $active = $DB->get_records_sql($sql, ['docid' => $docid, 'cutoff' => $cutoff]);

            $editors = [];
            foreach ($active as $a) {
                $editors[] = [
                    'userid' => (int)$a->userid,
                    'fullname' => fullname($a),
                    'is_me' => ($a->userid == $USER->id),
                    'sesskey' => $a->sesskey,
                ];
            }
            return $editors;
        } catch (\Exception $e) {
            return [
                [
                    'userid' => (int)($USER->id ?? 0),
                    'fullname' => fullname($USER),
                    'is_me' => true,
                    'sesskey' => $sesskey
                ]
            ];
        }
    }

    /**
     * Gets default filename for document type.
     * @param string $doctype Document type string
     * @return string Filename
     */
    public static function get_default_filename(string $doctype): string {
        if ($doctype === 'spreadsheet' || $doctype === 'calc' || $doctype === 'excel') {
            return 'document.xlsx';
        } else if ($doctype === 'pdf') {
            return 'document.pdf';
        } else if ($doctype === 'presentation' || $doctype === 'deck' || $doctype === 'pptx') {
            return 'document.pptx';
        }
        return 'document.docx';
    }

    /**
     * Gets default initial file content for new documents.
     * @param string $doctype Document type string
     * @return string Initial content string
     */
    public static function get_default_file_content(string $doctype): string {
        if ($doctype === 'pdf') {
            return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000102 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
        }
        return "CraftOffice Document Data";
    }

    /**
     * Checks if current user has permission to view document.
     * @param stdClass $doc Document record
     * @return bool True if permitted
     */
    public static function can_view(stdClass $doc): bool {
        global $DB, $USER;

        if ($doc->accesslevel == 0) return true; // Public (Anyone including visitors / guests)
        if (!isloggedin() || isguestuser()) return false;
        if (is_siteadmin()) return true;
        if ($doc->usermodified == $USER->id) return true; // Author
        if ($doc->accesslevel == 1) return true; // Authenticated Moodle users
        if ($doc->accesslevel == 3) {            // Shared with specific users
            try {
                return $DB->record_exists('local_craftoffice_shares', ['docid' => $doc->id, 'userid' => $USER->id]);
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Checks if current user has permission to edit / overwrite document on Moodle server.
     * @param stdClass $doc Document record
     * @return bool True if permitted
     */
    public static function can_edit(stdClass $doc): bool {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return false; // Visitors can view public docs, but cannot overwrite server files
        }

        if (is_siteadmin()) return true;
        if ($doc->usermodified == $USER->id) return true; // Author

        if ($doc->accesslevel == 1 || $doc->accesslevel == 0) {
            return true;
        }

        if ($doc->accesslevel == 3) { // Shared with specific users
            try {
                return $DB->record_exists('local_craftoffice_shares', ['docid' => $doc->id, 'userid' => $USER->id]);
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Saves list of shared user IDs for a document.
     * @param int $docid Document ID
     * @param array $userids Array of Moodle user IDs
     */
    public static function save_shared_users(int $docid, array $userids) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_craftoffice_shares')) {
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
            } catch (\Exception $e) {}
        }

        try {
            $DB->delete_records('local_craftoffice_shares', ['docid' => $docid]);
            foreach ($userids as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) {
                    $rec = new stdClass();
                    $rec->docid = $docid;
                    $rec->userid = $uid;
                    $rec->timecreated = time();
                    $DB->insert_record('local_craftoffice_shares', $rec);
                }
            }
        } catch (\Exception $e) {}
    }

    /**
     * Gets list of user IDs shared for a document.
     * @param int $docid Document ID
     * @return array Array of user IDs
     */
    public static function get_shared_users(int $docid): array {
        global $DB;
        try {
            return array_keys($DB->get_records('local_craftoffice_shares', ['docid' => $docid], '', 'userid'));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Magic getter for data property.
     * @param string $item Field name
     * @return mixed Field value
     */
    public function __get($item) {
        if (isset($this->_data->$item)) {
            return $this->_data->$item;
        }
        return null;
    }
}
