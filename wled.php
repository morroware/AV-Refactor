<?php
/**
 * WLED Control Handler - Entry Point
 *
 * Single entry point for WLED control.
 * Accepts zone parameter to determine which zone's WLED devices to control.
 *
 * @author Seth Morrow
 * @version 3.2 (Dynamic Zone Support)
 */

require_once __DIR__ . '/shared/zones.php';

// Get valid zones from configuration
$validZones = getEnabledZoneIds();

// Get zone from POST or GET parameter
$zone = $_POST['zone'] ?? $_GET['zone'] ?? null;

if (!$zone || !in_array($zone, $validZones)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing zone parameter. Valid zones: ' . implode(', ', $validZones)
    ]);
    exit;
}

// Set ZONE_DIR to the selected zone's directory
define('ZONE_DIR', __DIR__ . '/' . $zone);

// Verify zone directory exists
if (!is_dir(ZONE_DIR)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Zone directory not found: ' . $zone
    ]);
    exit;
}

// Include the shared implementation
require_once __DIR__ . '/shared/wled.php';
