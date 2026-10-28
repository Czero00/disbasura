<?php
/** Keep dispute pages compatible with both the original and newer table layouts. */
function ensure_dispute_schema(PDO $db): array {
    $columns = array_column($db->query('SHOW COLUMNS FROM disputes')->fetchAll(), 'Field');

    if (!in_array('proof_photo', $columns, true)) {
        $db->exec('ALTER TABLE disputes ADD COLUMN proof_photo VARCHAR(500) NULL');
        if (in_array('photo', $columns, true)) $db->exec('UPDATE disputes SET proof_photo=photo WHERE proof_photo IS NULL');
        $columns[] = 'proof_photo';
    }
    if (!in_array('description', $columns, true)) {
        $db->exec('ALTER TABLE disputes ADD COLUMN description TEXT NULL');
        if (in_array('note', $columns, true)) $db->exec('UPDATE disputes SET description=note WHERE description IS NULL');
        $columns[] = 'description';
    }
    if (!in_array('resolution', $columns, true)) {
        $db->exec('ALTER TABLE disputes ADD COLUMN resolution VARCHAR(32) NULL');
        if (in_array('admin_verdict', $columns, true)) $db->exec('UPDATE disputes SET resolution=admin_verdict WHERE resolution IS NULL');
        $columns[] = 'resolution';
    }
    if (!in_array('admin_id', $columns, true)) {
        $db->exec('ALTER TABLE disputes ADD COLUMN admin_id INT NULL');
        $columns[] = 'admin_id';
    }
    return $columns;
}
