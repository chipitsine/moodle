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
 * Upgrade script for the scorm module.
 *
 * @package    mod_scorm
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @global moodle_database $DB
 * @param int $oldversion
 * @return bool
 */
function xmldb_scorm_upgrade($oldversion) {
    global $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    // New table structure for scorm_scoes_track.
    if ($oldversion < 2023042401) {
        // Define table scorm_attempt to be created.
        $table = new xmldb_table('scorm_attempt');

        // Adding fields to table scorm_attempt.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scormid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table scorm_attempt.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('scorm', XMLDB_KEY_FOREIGN, ['scormid'], 'scorm', ['id']);

        // Conditionally launch create table for scorm_attempt.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table scorm_element to be created.
        $table = new xmldb_table('scorm_element');

        // Adding fields to table scorm_element.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('element', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table scorm_element.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table scorm_element.
        $table->add_index('element', XMLDB_INDEX_UNIQUE, ['element']);

        // Conditionally launch create table for scorm_element.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table scorm_scoes_value to be created.
        $table = new xmldb_table('scorm_scoes_value');

        // Adding fields to table scorm_scoes_value.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('elementid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table scorm_scoes_value.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('scoe', XMLDB_KEY_FOREIGN, ['scoid'], 'scorm_scoes', ['id']);
        $table->add_key('attempt', XMLDB_KEY_FOREIGN, ['attemptid'], 'scorm_attempt', ['id']);
        $table->add_key('element', XMLDB_KEY_FOREIGN, ['elementid'], 'scorm_element', ['id']);

        // Conditionally launch create table for scorm_scoes_value.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2023042401, 'scorm');
    }

    if ($oldversion < 2023042402) {
        $trans = $DB->start_delegated_transaction();

        // First grab all elements and store those.
        $sql = "INSERT INTO {scorm_element} (element)
                    SELECT DISTINCT element FROM {scorm_scoes_track}";
        $DB->execute($sql);

        // Now store all data in the scorm_attempt table.
        $sql = "INSERT INTO {scorm_attempt} (userid, scormid, attempt)
                    SELECT DISTINCT userid, scormid, attempt FROM {scorm_scoes_track}";
        $DB->execute($sql);

        $trans->allow_commit();
        // Scorm savepoint reached.
        upgrade_mod_savepoint(true, 2023042402, 'scorm');
    }
    if ($oldversion < 2023042403) {
        // Now store all translated data in the scorm_scoes_value table.
        $total = $DB->count_records('scorm_scoes_track');
        if ($total > 500000) {
            // This site has a large number of user track records, lets warn that this next part may take some time.
            $notification = new \core\output\notification(
                get_string('largetrackupgrade', 'scorm', format_float($total, 0)),
                \core\output\notification::NOTIFY_WARNING
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // We don't need a progress bar - just run the fastest option possible.
        $sql = "INSERT INTO {scorm_scoes_value} (attemptid, scoid, elementid, value, timemodified)
                SELECT a.id as attemptid, t.scoid as scoid, e.id as elementid, t.value as value, t.timemodified
                  FROM {scorm_scoes_track} t
                  JOIN {scorm_element} e ON e.element = t.element
                  JOIN {scorm_attempt} a ON (t.userid = a.userid AND t.scormid = a.scormid AND a.attempt = t.attempt)";
        $DB->execute($sql);

        // Drop old table scorm_scoes_track.
        $table = new xmldb_table('scorm_scoes_track');

        // Conditionally launch drop table for scorm_scoes_track.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Scorm savepoint reached.
        upgrade_mod_savepoint(true, 2023042403, 'scorm');
    }

    // Automatically generated Moodle v4.3.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
