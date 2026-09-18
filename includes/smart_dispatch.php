<?php
/**
 * ============================================================
 *  AI-Powered Smart Dispatch Recommendation Module
 * ============================================================
 * Implements DisBasura Capstone 1 manuscript, "AI-Powered Smart
 * Dispatch Recommendation Module" (Development/Construction Phase).
 *
 * Per the manuscript's own design justification, this is
 * DELIBERATELY a lightweight, rule-based scoring algorithm —
 * NOT a machine learning model — because:
 *   1. A local XAMPP deployment has no infrastructure to train/run
 *      a real-time neural network.
 *   2. A scoring algorithm is fully explainable: the admin can see
 *      exactly why a collector was recommended, which builds trust.
 *   3. There isn't yet enough historical usage data to train a
 *      meaningful ML model — that becomes viable in a future version.
 *
 * It ranks every AVAILABLE collector against a new/pending request
 * using three weighted factors (proximity, current workload,
 * performance rating), and returns a ranked list. The admin always
 * reviews the top suggestion and can confirm or override it — this
 * module never assigns anything on its own.
 */

define('DISPATCH_WEIGHT_PROXIMITY', 0.4);
define('DISPATCH_WEIGHT_WORKLOAD',  0.3);
define('DISPATCH_WEIGHT_RATING',    0.3);

/**
 * Returns collectors ranked best-to-worst for a given request.
 * Each entry: ['collector' => row, 'proximity'=>0-1, 'workload'=>0-1,
 *              'rating'=>0-1, 'score'=>0-100, 'reason'=>string]
 */
function get_smart_dispatch_recommendations(PDO $db, array $request): array {
    $collectors = $db->query("SELECT * FROM collectors WHERE status='available'")->fetchAll();
    if (!$collectors) return [];

    $scored = [];
    foreach ($collectors as $col) {
        $proximity = score_proximity($col, $request);
        $workload  = score_workload($db, (int)$col['id']);
        $rating    = score_rating($db, (int)$col['id']);

        $total = ($proximity * DISPATCH_WEIGHT_PROXIMITY)
               + ($workload  * DISPATCH_WEIGHT_WORKLOAD)
               + ($rating    * DISPATCH_WEIGHT_RATING);

        $scored[] = [
            'collector' => $col,
            'proximity' => $proximity,
            'workload'  => $workload,
            'rating'    => $rating,
            'score'     => round($total * 100, 1),
            'reason'    => build_dispatch_reason($col, $request, $proximity, $workload, $rating),
        ];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    return $scored;
}

/**
 * Factor 1: Proximity.
 * Requests are captured at sitio level (not GPS lat/lng), so a
 * collector already assigned to the SAME sitio as the request is
 * treated as closest. This is the practical proxy available given
 * the current data model — true GPS-distance scoring becomes
 * possible once tracking_history has enough logged points per sitio.
 */
function score_proximity(array $collector, array $request): float {
    $colSitio = trim($collector['sitio'] ?? '');
    $reqSitio = trim($request['sitio'] ?? '');
    return (strcasecmp($colSitio, $reqSitio) === 0) ? 1.0 : 0.4;
}

/**
 * Factor 2: Current workload.
 * Fewer currently-active ('assigned' but not yet 'completed')
 * requests already on this collector's plate = higher score.
 */
function score_workload(PDO $db, int $collectorId): float {
    $stmt = $db->prepare("SELECT COUNT(*) FROM requests WHERE collector_id=? AND status='assigned'");
    $stmt->execute([$collectorId]);
    $active = (int)$stmt->fetchColumn();
    return max(0.0, 1 - ($active * 0.25));
}

/**
 * Factor 3: Performance rating.
 * Average resident star rating (1–5) across this collector's
 * completed requests, normalized to 0–1. A collector with no
 * ratings yet gets a neutral 0.7 so new/unrated collectors aren't
 * unfairly buried at the bottom of every recommendation.
 */
function score_rating(PDO $db, int $collectorId): float {
    $stmt = $db->prepare(
        "SELECT AVG(ra.stars) FROM ratings ra
         JOIN requests r ON ra.request_id = r.id
         WHERE r.collector_id = ?"
    );
    $stmt->execute([$collectorId]);
    $avg = $stmt->fetchColumn();
    return ($avg !== null && $avg !== false) ? ((float)$avg / 5) : 0.7;
}

/** Human-readable, explainable reason string for the admin UI. */
function build_dispatch_reason(array $col, array $request, float $prox, float $work, float $rate): string {
    $bits   = [];
    $bits[] = $prox >= 1.0 ? "already in {$col['sitio']}" : "outside this sitio";
    $bits[] = $work >= 0.75 ? "light workload" : ($work >= 0.5 ? "moderate workload" : "busy queue");
    $bits[] = $rate > 0.7 ? "strong rating" : ($rate == 0.7 ? "no rating yet" : "mixed rating history");
    return implode(' · ', $bits);
}
