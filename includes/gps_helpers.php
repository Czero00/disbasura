<?php
const GPS_STALE_AFTER_SECONDS = 120;
const GPS_HISTORY_SAMPLE_SECONDS = 30;

function ensure_tracking_history_schema(PDO $db): void {
    static $ready = false;
    if ($ready) return;

    $columns = $db->query('SHOW COLUMNS FROM tracking_history')->fetchAll(PDO::FETCH_COLUMN);
    // Some existing DisBasura databases use lat/lng/recorded_at. Keep their
    // data and add the canonical columns expected by the route-history code.
    if (!in_array('latitude', $columns, true)) {
        $db->exec('ALTER TABLE tracking_history ADD COLUMN latitude DECIMAL(10,8) NULL');
        if (in_array('lat', $columns, true)) $db->exec('UPDATE tracking_history SET latitude=lat WHERE latitude IS NULL');
        $columns[] = 'latitude';
    }
    if (!in_array('longitude', $columns, true)) {
        $db->exec('ALTER TABLE tracking_history ADD COLUMN longitude DECIMAL(11,8) NULL');
        if (in_array('lng', $columns, true)) $db->exec('UPDATE tracking_history SET longitude=lng WHERE longitude IS NULL');
        $columns[] = 'longitude';
    }
    if (!in_array('timestamp', $columns, true)) {
        $db->exec('ALTER TABLE tracking_history ADD COLUMN `timestamp` DATETIME NULL');
        if (in_array('recorded_at', $columns, true)) $db->exec('UPDATE tracking_history SET `timestamp`=recorded_at WHERE `timestamp` IS NULL');
        else $db->exec('UPDATE tracking_history SET `timestamp`=CURRENT_TIMESTAMP WHERE `timestamp` IS NULL');
        $columns[] = 'timestamp';
    }
    if (!in_array('accuracy', $columns, true)) {
        $db->exec('ALTER TABLE tracking_history ADD COLUMN accuracy DECIMAL(7,2) NULL AFTER longitude');
        $columns[] = 'accuracy';
    }
    if (!in_array('client_point_id', $columns, true)) {
        $db->exec('ALTER TABLE tracking_history ADD COLUMN client_point_id CHAR(36) NULL AFTER accuracy');
    }
    $indexes = $db->query('SHOW INDEX FROM tracking_history')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('uq_tracking_history_point', $indexes, true)) {
        $db->exec('ALTER TABLE tracking_history ADD UNIQUE KEY uq_tracking_history_point (collector_id, client_point_id)');
    }
    if (!in_array('idx_tracking_history_time', $indexes, true)) {
        $db->exec('ALTER TABLE tracking_history ADD KEY idx_tracking_history_time (`timestamp`)');
    }
    $ready = true;
}

function get_visible_truck_locations(PDO $db, ?string $sitio = null): array {
    $sql = 'SELECT id,full_name,sitio,status,truck_lat,truck_lng,truck_updated_at FROM collectors
            WHERE truck_lat IS NOT NULL AND truck_lng IS NOT NULL
              AND truck_lat BETWEEN 10.15 AND 10.52 AND truck_lng BETWEEN 123.70 AND 124.12';
    if ($sitio !== null) {
        $stmt = $db->prepare($sql . ' AND sitio=? ORDER BY truck_updated_at DESC');
        $stmt->execute([$sitio]);
        $trucks = $stmt->fetchAll();
    } else {
        $trucks = $db->query($sql . ' ORDER BY truck_updated_at DESC')->fetchAll();
    }

    $manila = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $manila);
    foreach ($trucks as &$truck) {
        try {
            $updated = new DateTimeImmutable((string)$truck['truck_updated_at'], $manila);
            $age = max(0, $now->getTimestamp() - $updated->getTimestamp());
        } catch (Throwable $e) {
            $age = GPS_STALE_AFTER_SECONDS + 1;
        }
        $truck['gps_age_seconds'] = $age;
        $truck['gps_fresh'] = $age <= GPS_STALE_AFTER_SECONDS;
        if (!$truck['gps_fresh']) {
            $truck['truck_lat'] = null;
            $truck['truck_lng'] = null;
        }
    }
    unset($truck);
    return $trucks;
}

function normalize_gps_capture_time(string $value): ?string {
    try {
        $capture = new DateTimeImmutable($value);
        $capture = $capture->setTimezone(new DateTimeZone('Asia/Manila'));
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $age = $now->getTimestamp() - $capture->getTimestamp();
        if ($age < -120 || $age > 86400) return null;
        return $capture->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}
