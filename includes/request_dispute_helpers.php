<?php

function ensure_request_disputes_table(PDO $db): void {
    static $ready = false;
    if ($ready) return;

    $db->exec("CREATE TABLE IF NOT EXISTS pickup_request_disputes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        collector_id INT NULL,
        admin_id INT NULL,
        description TEXT NOT NULL,
        proof_photo VARCHAR(500) NULL,
        status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
        resolution ENUM('confirm','reopen') NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        UNIQUE KEY uq_pickup_request_dispute (request_id, resident_id),
        INDEX idx_pickup_request_disputes_status (status, created_at),
        CONSTRAINT fk_pickup_dispute_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_pickup_dispute_resident FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_pickup_dispute_collector FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL,
        CONSTRAINT fk_pickup_dispute_admin FOREIGN KEY (admin_id) REFERENCES administrators(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ready = true;
}
