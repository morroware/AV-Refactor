<?php
/**
 * Configuration Files Editor - Entry Point
 *
 * Single entry point for editing zone configuration files.
 * Accepts zone parameter to determine which zone's files to edit.
 *
 * @author Seth Morrow
 * @version 3.2 (Dynamic Zone Support)
 */

require_once __DIR__ . '/shared/zones.php';

// Get valid zones from configuration
$validZones = getEnabledZoneIds();

// Get and validate zone parameter
$zone = $_GET['zone'] ?? null;

if (!$zone || !in_array($zone, $validZones)) {
    // Show zone selection page if no valid zone provided
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configuration Files Editor - Select Zone</title>
        <link rel="stylesheet" href="shared/styles.css">
        <style>
            .zone-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin: 2rem 0;
            }
            .zone-card {
                background: var(--surface-color);
                padding: 2rem;
                border-radius: 8px;
                text-align: center;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .zone-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
            }
            .zone-card a {
                color: var(--primary-color);
                text-decoration: none;
                font-size: 1.3em;
                font-weight: 600;
            }
            .zone-card a:hover {
                color: var(--secondary-color);
            }
        </style>
    </head>
    <body>
        <div class="content-wrapper">
            <header>
                <div class="logo-title-group">
                    <h1>Configuration Files Editor</h1>
                </div>
            </header>

            <div class="main-container">
                <h2>Select a Zone to Configure</h2>
                <div class="zone-grid">
                    <?php
                    $allZones = getNavigationZones();
                    foreach ($allZones as $zoneConfig):
                        $zoneId = $zoneConfig['id'];
                        $zoneName = $zoneConfig['name'] ?? ucfirst($zoneId);
                    ?>
                    <div class="zone-card">
                        <a href="?zone=<?php echo urlencode($zoneId); ?>">
                            <?php echo htmlspecialchars($zoneName); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="text-align: center; margin-top: 2rem;">
                    <a href="index.html" style="color: var(--primary-color);">Back to Home</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Set ZONE_DIR to the selected zone's directory
define('ZONE_DIR', __DIR__ . '/' . $zone);

// Verify zone directory exists
if (!is_dir(ZONE_DIR)) {
    http_response_code(404);
    die("Zone directory not found: " . htmlspecialchars($zone));
}

// Include the shared implementation
require_once __DIR__ . '/shared/editini.php';
