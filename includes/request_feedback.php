<?php

function ensure_request_feedback_table(PDO $db): void {
    static $ready = false;
    if ($ready) return;

    $db->exec("CREATE TABLE IF NOT EXISTS pickup_request_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        collector_id INT NULL,
        resident_id INT NOT NULL,
        stars TINYINT UNSIGNED NOT NULL,
        comment TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pickup_request_feedback (request_id, resident_id),
        INDEX idx_pickup_request_feedback_collector (collector_id),
        CONSTRAINT fk_pickup_feedback_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_pickup_feedback_collector FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL,
        CONSTRAINT fk_pickup_feedback_resident FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ready = true;
}
