<?php
/**
 * Zone Manager - Add, Edit, Remove, and Duplicate Zones
 *
 * Provides a user-friendly interface for managing AV system zones.
 * All zone configuration is stored in zones.json.
 *
 * @author Seth Morrow
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/shared/zones.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $result = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'add':
            $zoneData = [
                'id' => $_POST['id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'showInNav' => isset($_POST['showInNav']),
                'icon' => $_POST['icon'] ?? 'default',
                'color' => $_POST['color'] ?? '#00C853'
            ];

            // Sanitize zone ID first (same logic as addZone)
            $zoneId = preg_replace('/[^a-z0-9]/', '', strtolower($zoneData['id']));
            if (empty($zoneId)) {
                $result = ['success' => false, 'message' => 'Zone ID must contain alphanumeric characters'];
                break;
            }

            // Check if zone already exists in config
            $existingZone = getZoneById($zoneId);
            if ($existingZone) {
                $result = ['success' => false, 'message' => 'Zone ID already exists'];
                break;
            }

            // CREATE DIRECTORY FIRST to prevent race condition
            $copyFrom = !empty($_POST['copyFrom']) ? $_POST['copyFrom'] : null;
            $dirResult = createZoneDirectory($zoneId, $copyFrom);
            if (!$dirResult['success']) {
                $result = $dirResult;
                break;
            }

            // Directory created successfully, now save to config
            $zoneData['id'] = $zoneId; // Use sanitized ID
            $result = addZone($zoneData);

            // If config save failed, rollback by deleting the directory
            if (!$result['success']) {
                deleteDirectory(dirname(__DIR__) . '/' . $zoneId);
            }
            break;

        case 'update':
            $zoneId = $_POST['id'] ?? '';
            $updates = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'enabled' => isset($_POST['enabled']),
                'showInNav' => isset($_POST['showInNav']),
                'icon' => $_POST['icon'] ?? 'default',
                'color' => $_POST['color'] ?? '#00C853'
            ];
            $result = updateZone($zoneId, $updates);
            break;

        case 'delete':
            $zoneId = $_POST['id'] ?? '';
            $deleteDir = isset($_POST['deleteDirectory']);
            $result = removeZone($zoneId, $deleteDir);
            break;

        case 'duplicate':
            $sourceId = $_POST['sourceId'] ?? '';
            $newId = $_POST['newId'] ?? '';
            $newName = $_POST['newName'] ?? '';
            $result = duplicateZone($sourceId, $newId, $newName);
            break;

        case 'reorder':
            $orderData = $_POST['order'] ?? '';
            $orderedIds = json_decode($orderData, true);
            if (is_array($orderedIds)) {
                $result = reorderZones($orderedIds);
            } else {
                $result = ['success' => false, 'message' => 'Invalid order data'];
            }
            break;

        case 'getZones':
            $config = loadZonesConfig();
            $result = ['success' => true, 'zones' => $config['zones'] ?? []];
            break;

        // Quick Links actions
        case 'addQuickLink':
            $linkData = [
                'id' => $_POST['id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'url' => $_POST['url'] ?? '',
                'description' => $_POST['description'] ?? '',
                'showInNav' => isset($_POST['showInNav']),
                'color' => $_POST['color'] ?? '#2196F3',
                'openInNewTab' => isset($_POST['openInNewTab'])
            ];
            $result = addQuickLink($linkData);
            break;

        case 'updateQuickLink':
            $linkId = $_POST['id'] ?? '';
            $updates = [
                'name' => $_POST['name'] ?? '',
                'url' => $_POST['url'] ?? '',
                'description' => $_POST['description'] ?? '',
                'enabled' => isset($_POST['enabled']),
                'showInNav' => isset($_POST['showInNav']),
                'color' => $_POST['color'] ?? '#2196F3',
                'openInNewTab' => isset($_POST['openInNewTab'])
            ];
            $result = updateQuickLink($linkId, $updates);
            break;

        case 'deleteQuickLink':
            $linkId = $_POST['id'] ?? '';
            $result = removeQuickLink($linkId);
            break;

        case 'reorderQuickLinks':
            $orderData = $_POST['order'] ?? '';
            $orderedIds = json_decode($orderData, true);
            if (is_array($orderedIds)) {
                $result = reorderQuickLinks($orderedIds);
            } else {
                $result = ['success' => false, 'message' => 'Invalid order data'];
            }
            break;

        case 'getQuickLinks':
            $config = loadZonesConfig();
            $result = ['success' => true, 'quickLinks' => $config['specialLinks'] ?? []];
            break;
    }

    echo json_encode($result);
    exit;
}

// Load current configuration for display
$config = loadZonesConfig();
$zones = $config['zones'] ?? [];
$quickLinks = $config['specialLinks'] ?? [];
$settings = $config['settings'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zone Manager - Castle AV Controls</title>
    <link rel="stylesheet" href="shared/styles.css">
    <!-- LiveCode browser widget compatibility layer -->
    <script src="livecode-compat.js"></script>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <div class="logo-container">
                    <a href="index.html">
                        <img src="logo.png" alt="Castle AV Controls Logo" class="logo">
                    </a>
                </div>
                <h1>Zone Manager</h1>
            </div>

            <div class="header-buttons">
                <a href="index.html" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
            </div>
        </header>

        <div class="zone-manager">
            <div id="alert-container"></div>

            <!-- Collapsible instructions toggle for mobile -->
            <button class="instructions-toggle" id="instructions-toggle" onclick="toggleInstructions()">
                <span>View Instructions</span>
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>

            <div class="instructions" id="instructions">
                <h3>Zone Management Instructions</h3>
                <ul>
                    <li><strong>Add Zone:</strong> Create a new zone with custom settings. Optionally copy configuration from an existing zone.</li>
                    <li><strong>Edit Zone:</strong> Modify zone name, description, visibility, and appearance settings.</li>
                    <li><strong>Duplicate Zone:</strong> Create a complete copy of an existing zone with all its configuration files.</li>
                    <li><strong>Delete Zone:</strong> Remove a zone from the system. You can choose to keep or delete the zone's files.</li>
                    <li><strong>Reorder:</strong> Drag and drop zones to change their display order in the navigation.</li>
                    <li><strong>Enable/Disable:</strong> Toggle zones on or off without deleting them.</li>
                </ul>
            </div>

            <div class="manager-header">
                <h2>Configured Zones</h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add New Zone
                    </button>
                    <button class="btn btn-secondary" onclick="saveOrder()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" />
                        </svg>
                        Save Order
                    </button>
                </div>
            </div>

            <table class="zones-table" id="zones-table">
                <thead>
                    <tr>
                        <th width="40"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th>Status</th>
                        <th>Navigation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="zones-tbody">
                    <?php foreach ($zones as $zone):
                        $zoneColor = htmlspecialchars($zone['color'] ?? '#6366f1');
                    ?>
                    <tr data-id="<?php echo htmlspecialchars($zone['id']); ?>"
                        class="<?php echo empty($zone['enabled']) ? 'disabled' : ''; ?>"
                        style="--zone-color: <?php echo $zoneColor; ?>">
                        <td data-label="">
                            <span class="drag-handle" title="Drag to reorder">&#9776;</span>
                            <div class="reorder-buttons">
                                <button type="button" class="reorder-btn" onclick="moveRow(this, 'up')" title="Move up">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                                </button>
                                <button type="button" class="reorder-btn" onclick="moveRow(this, 'down')" title="Move down">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </td>
                        <td data-label="ID"><code><?php echo htmlspecialchars($zone['id']); ?></code></td>
                        <td data-label="Name"><?php echo htmlspecialchars($zone['name'] ?? ''); ?></td>
                        <td data-label="Description"><?php echo htmlspecialchars($zone['description'] ?? ''); ?></td>
                        <td data-label="Color">
                            <span class="zone-color" style="background: <?php echo $zoneColor; ?>"></span>
                        </td>
                        <td data-label="Status">
                            <span class="zone-status <?php echo !empty($zone['enabled']) ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo !empty($zone['enabled']) ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td data-label="Navigation">
                            <?php echo !empty($zone['showInNav']) ? 'Visible' : 'Hidden'; ?>
                        </td>
                        <td data-label="Actions" class="zone-actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal('<?php echo htmlspecialchars($zone['id']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                                Edit
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="openDuplicateModal('<?php echo htmlspecialchars($zone['id']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"/>
                                    <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"/>
                                </svg>
                                Copy
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteModal('<?php echo htmlspecialchars($zone['id']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Quick Links Section -->
            <div class="section-divider"></div>

            <div class="manager-header">
                <h2>Quick Links</h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddLinkModal()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add Quick Link
                    </button>
                    <button class="btn btn-secondary" onclick="saveLinkOrder()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" />
                        </svg>
                        Save Order
                    </button>
                </div>
            </div>

            <p class="section-description">Quick links appear as buttons on the home page and can link to any URL (external dashboards, tools, etc.)</p>

            <table class="zones-table" id="quicklinks-table">
                <thead>
                    <tr>
                        <th width="40"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Color</th>
                        <th>Status</th>
                        <th>New Tab</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="quicklinks-tbody">
                    <?php foreach ($quickLinks as $link):
                        $linkColor = htmlspecialchars($link['color'] ?? '#6366f1');
                    ?>
                    <tr data-id="<?php echo htmlspecialchars($link['id']); ?>"
                        class="<?php echo empty($link['enabled']) ? 'disabled' : ''; ?>"
                        style="--zone-color: <?php echo $linkColor; ?>">
                        <td data-label="">
                            <span class="drag-handle link-drag" title="Drag to reorder">&#9776;</span>
                            <div class="reorder-buttons">
                                <button type="button" class="reorder-btn" onclick="moveLinkRow(this, 'up')" title="Move up">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                                </button>
                                <button type="button" class="reorder-btn" onclick="moveLinkRow(this, 'down')" title="Move down">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </td>
                        <td data-label="ID"><code><?php echo htmlspecialchars($link['id']); ?></code></td>
                        <td data-label="Name"><?php echo htmlspecialchars($link['name'] ?? ''); ?></td>
                        <td data-label="URL" class="url-cell"><a href="<?php echo htmlspecialchars($link['url'] ?? '#'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($link['url'] ?? ''); ?></a></td>
                        <td data-label="Color">
                            <span class="zone-color" style="background: <?php echo $linkColor; ?>"></span>
                        </td>
                        <td data-label="Status">
                            <span class="zone-status <?php echo !empty($link['enabled']) ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo !empty($link['enabled']) ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td data-label="New Tab">
                            <?php echo !empty($link['openInNewTab']) ? 'Yes' : 'No'; ?>
                        </td>
                        <td data-label="Actions" class="zone-actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditLinkModal('<?php echo htmlspecialchars($link['id']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                                Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteLinkModal('<?php echo htmlspecialchars($link['id']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($quickLinks)): ?>
                    <tr class="empty-row">
                        <td colspan="8">
                            No quick links configured. Click "Add Quick Link" to create one.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Zone Modal -->
    <div class="modal" id="add-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Zone</h2>
                <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
            </div>
            <form id="add-form" onsubmit="submitAddForm(event)">
                <div class="form-group">
                    <label for="add-id">Zone ID</label>
                    <input type="text" id="add-id" name="id" required pattern="[a-z0-9]+" placeholder="e.g., lounge">
                    <p class="form-hint">Lowercase letters and numbers only. This becomes the folder name.</p>
                </div>
                <div class="form-group">
                    <label for="add-name">Display Name</label>
                    <input type="text" id="add-name" name="name" required placeholder="e.g., VIP Lounge">
                </div>
                <div class="form-group">
                    <label for="add-description">Description</label>
                    <textarea id="add-description" name="description" placeholder="Optional description of this zone"></textarea>
                </div>
                <div class="form-group">
                    <label for="add-color">Button Color</label>
                    <input type="color" id="add-color" name="color" value="#00C853">
                </div>
                <div class="form-group">
                    <label for="add-copyFrom">Copy Configuration From</label>
                    <select id="add-copyFrom" name="copyFrom">
                        <option value="">Start with empty configuration</option>
                        <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo htmlspecialchars($zone['id']); ?>">
                            <?php echo htmlspecialchars($zone['name'] ?? $zone['id']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Copy receivers, transmitters, and other settings from an existing zone.</p>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="showInNav" checked>
                        Show in navigation
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Zone</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Zone Modal -->
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Zone</h2>
                <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
            </div>
            <form id="edit-form" onsubmit="submitEditForm(event)">
                <input type="hidden" id="edit-id" name="id">
                <div class="form-group">
                    <label>Zone ID</label>
                    <input type="text" id="edit-id-display" disabled>
                    <p class="form-hint">Zone ID cannot be changed after creation.</p>
                </div>
                <div class="form-group">
                    <label for="edit-name">Display Name</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea id="edit-description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-color">Button Color</label>
                    <input type="color" id="edit-color" name="color">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit-enabled" name="enabled">
                        Zone Enabled
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit-showInNav" name="showInNav">
                        Show in navigation
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Duplicate Zone Modal -->
    <div class="modal" id="duplicate-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Duplicate Zone</h2>
                <button class="modal-close" onclick="closeModal('duplicate-modal')">&times;</button>
            </div>
            <form id="duplicate-form" onsubmit="submitDuplicateForm(event)">
                <input type="hidden" id="duplicate-sourceId" name="sourceId">
                <div class="form-group">
                    <label>Source Zone</label>
                    <input type="text" id="duplicate-source-display" disabled>
                </div>
                <div class="form-group">
                    <label for="duplicate-newId">New Zone ID</label>
                    <input type="text" id="duplicate-newId" name="newId" required pattern="[a-z0-9]+" placeholder="e.g., lounge2">
                    <p class="form-hint">Lowercase letters and numbers only.</p>
                </div>
                <div class="form-group">
                    <label for="duplicate-newName">New Display Name</label>
                    <input type="text" id="duplicate-newName" name="newName" required placeholder="e.g., VIP Lounge 2">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('duplicate-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Duplicate Zone</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Zone Modal -->
    <div class="modal" id="delete-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="delete-title">Delete Zone</h2>
                <button class="modal-close" onclick="closeModal('delete-modal')" aria-label="Close">&times;</button>
            </div>
            <form id="delete-form" onsubmit="submitDeleteForm(event)">
                <input type="hidden" id="delete-id" name="id">
                <div class="delete-warning">
                    <p>
                        Are you sure you want to delete the zone <strong id="delete-zone-name"></strong>?
                    </p>
                </div>
                <p class="section-description">This will remove the zone from navigation. You can choose whether to keep or delete its configuration files.</p>
                <div class="form-group">
                    <label class="checkbox-label delete-checkbox">
                        <input type="checkbox" id="delete-directory" name="deleteDirectory">
                        Also delete zone directory and all configuration files
                    </label>
                    <p class="form-hint delete-hint">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        This permanently deletes all zone files and cannot be undone!
                    </p>
                </div>
                <div class="form-group confirm-delete-group" id="confirm-delete-group">
                    <label for="confirm-delete-input">Type "<strong id="zone-id-to-confirm"></strong>" to confirm deletion:</label>
                    <input type="text" id="confirm-delete-input" placeholder="Type zone ID to confirm" autocomplete="off">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-modal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete-submit-btn">Delete Zone</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Quick Link Modal -->
    <div class="modal" id="add-link-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Quick Link</h2>
                <button class="modal-close" onclick="closeModal('add-link-modal')">&times;</button>
            </div>
            <form id="add-link-form" onsubmit="submitAddLinkForm(event)">
                <div class="form-group">
                    <label for="add-link-id">Link ID</label>
                    <input type="text" id="add-link-id" name="id" required pattern="[a-z0-9\-]+" placeholder="e.g., my-dashboard">
                    <p class="form-hint">Lowercase letters, numbers, and hyphens only.</p>
                </div>
                <div class="form-group">
                    <label for="add-link-name">Display Name</label>
                    <input type="text" id="add-link-name" name="name" required placeholder="e.g., My Dashboard">
                </div>
                <div class="form-group">
                    <label for="add-link-url">URL</label>
                    <input type="text" id="add-link-url" name="url" required placeholder="e.g., https://example.com/dashboard">
                    <p class="form-hint">Full URL or relative path (e.g., "dashboard/" for local paths)</p>
                </div>
                <div class="form-group">
                    <label for="add-link-description">Description</label>
                    <textarea id="add-link-description" name="description" placeholder="Optional description"></textarea>
                </div>
                <div class="form-group">
                    <label for="add-link-color">Button Color</label>
                    <input type="color" id="add-link-color" name="color" value="#2196F3">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="showInNav" checked>
                        Show on home page
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="openInNewTab">
                        Open in new tab
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-link-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quick Link</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Quick Link Modal -->
    <div class="modal" id="edit-link-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Quick Link</h2>
                <button class="modal-close" onclick="closeModal('edit-link-modal')">&times;</button>
            </div>
            <form id="edit-link-form" onsubmit="submitEditLinkForm(event)">
                <input type="hidden" id="edit-link-id" name="id">
                <div class="form-group">
                    <label>Link ID</label>
                    <input type="text" id="edit-link-id-display" disabled>
                    <p class="form-hint">Link ID cannot be changed after creation.</p>
                </div>
                <div class="form-group">
                    <label for="edit-link-name">Display Name</label>
                    <input type="text" id="edit-link-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit-link-url">URL</label>
                    <input type="text" id="edit-link-url" name="url" required>
                </div>
                <div class="form-group">
                    <label for="edit-link-description">Description</label>
                    <textarea id="edit-link-description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-link-color">Button Color</label>
                    <input type="color" id="edit-link-color" name="color">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit-link-enabled" name="enabled">
                        Link Enabled
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit-link-showInNav" name="showInNav">
                        Show on home page
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit-link-openInNewTab" name="openInNewTab">
                        Open in new tab
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-link-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Quick Link Modal -->
    <div class="modal" id="delete-link-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="delete-title">Delete Quick Link</h2>
                <button class="modal-close" onclick="closeModal('delete-link-modal')" aria-label="Close">&times;</button>
            </div>
            <form id="delete-link-form" onsubmit="submitDeleteLinkForm(event)">
                <input type="hidden" id="delete-link-id" name="id">
                <div class="delete-warning">
                    <p>
                        Are you sure you want to delete the quick link <strong id="delete-link-name"></strong>?
                    </p>
                </div>
                <p class="section-description">This will remove the link from the home page.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-link-modal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Quick Link</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use LiveCode-compatible fetch if available
        const compatFetch = (window.LiveCodeCompat && window.LiveCodeCompat.fetch) || window.fetch.bind(window);

        // Zone data from PHP
        const zonesData = <?php echo json_encode($zones); ?>;
        const quickLinksData = <?php echo json_encode($quickLinks); ?>;

        // Toggle instructions visibility on mobile
        function toggleInstructions() {
            const toggle = document.getElementById('instructions-toggle');
            const instructions = document.getElementById('instructions');
            toggle.classList.toggle('active');
            instructions.classList.toggle('visible');

            // Update button text
            const span = toggle.querySelector('span');
            if (instructions.classList.contains('visible')) {
                span.textContent = 'Hide Instructions';
            } else {
                span.textContent = 'View Instructions';
            }
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openAddModal() {
            document.getElementById('add-form').reset();
            openModal('add-modal');
        }

        function openEditModal(zoneId) {
            const zone = zonesData.find(z => z.id === zoneId);
            if (!zone) return;

            document.getElementById('edit-id').value = zone.id;
            document.getElementById('edit-id-display').value = zone.id;
            document.getElementById('edit-name').value = zone.name || '';
            document.getElementById('edit-description').value = zone.description || '';
            document.getElementById('edit-color').value = zone.color || '#00C853';
            document.getElementById('edit-enabled').checked = zone.enabled !== false;
            document.getElementById('edit-showInNav').checked = zone.showInNav !== false;

            openModal('edit-modal');
        }

        function openDuplicateModal(zoneId) {
            const zone = zonesData.find(z => z.id === zoneId);
            if (!zone) return;

            document.getElementById('duplicate-sourceId').value = zone.id;
            document.getElementById('duplicate-source-display').value = zone.name || zone.id;
            document.getElementById('duplicate-newId').value = '';
            document.getElementById('duplicate-newName').value = '';

            openModal('duplicate-modal');
        }

        function openDeleteModal(zoneId) {
            const zone = zonesData.find(z => z.id === zoneId);
            if (!zone) return;

            document.getElementById('delete-id').value = zone.id;
            document.getElementById('delete-zone-name').textContent = zone.name || zone.id;
            document.getElementById('delete-directory').checked = false;
            document.getElementById('confirm-delete-group').classList.remove('visible');
            document.getElementById('confirm-delete-input').value = '';
            document.getElementById('zone-id-to-confirm').textContent = zone.id;
            document.getElementById('delete-submit-btn').disabled = false;

            // Show confirmation input when delete directory is checked
            document.getElementById('delete-directory').addEventListener('change', function() {
                const confirmGroup = document.getElementById('confirm-delete-group');
                const submitBtn = document.getElementById('delete-submit-btn');
                if (this.checked) {
                    confirmGroup.classList.add('visible');
                    submitBtn.disabled = true;
                } else {
                    confirmGroup.classList.remove('visible');
                    submitBtn.disabled = false;
                }
            });

            // Enable submit when confirmation matches
            document.getElementById('confirm-delete-input').addEventListener('input', function() {
                const submitBtn = document.getElementById('delete-submit-btn');
                const zoneId = document.getElementById('delete-id').value;
                submitBtn.disabled = this.value !== zoneId;
            });

            openModal('delete-modal');
        }

        // Alert functions
        function showAlert(message, type = 'success') {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Form submission functions
        async function submitAddForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'add');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('add-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        async function submitEditForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'update');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('edit-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        async function submitDuplicateForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'duplicate');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('duplicate-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        async function submitDeleteForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'delete');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('delete-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        // Drag and drop reordering
        let draggedRow = null;

        document.querySelectorAll('.drag-handle').forEach(handle => {
            const row = handle.closest('tr');

            handle.addEventListener('mousedown', () => {
                row.draggable = true;
            });

            handle.addEventListener('mouseup', () => {
                row.draggable = false;
            });
        });

        document.getElementById('zones-tbody').addEventListener('dragstart', (e) => {
            if (e.target.tagName === 'TR') {
                draggedRow = e.target;
                e.target.classList.add('dragging');
            }
        });

        document.getElementById('zones-tbody').addEventListener('dragend', (e) => {
            if (e.target.tagName === 'TR') {
                e.target.classList.remove('dragging');
                e.target.draggable = false;
                draggedRow = null;
            }
        });

        document.getElementById('zones-tbody').addEventListener('dragover', (e) => {
            e.preventDefault();
            const row = e.target.closest('tr');
            if (row && row !== draggedRow) {
                const rect = row.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    row.parentNode.insertBefore(draggedRow, row);
                } else {
                    row.parentNode.insertBefore(draggedRow, row.nextSibling);
                }
            }
        });

        async function saveOrder() {
            const rows = document.querySelectorAll('#zones-tbody tr');
            const order = Array.from(rows).map(row => row.dataset.id);

            const formData = new FormData();
            formData.append('action', 'reorder');
            formData.append('order', JSON.stringify(order));

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Zone order saved successfully');
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        // Mobile reorder functions for zones
        function moveRow(button, direction) {
            const row = button.closest('tr');
            const tbody = row.parentNode;

            if (direction === 'up' && row.previousElementSibling) {
                tbody.insertBefore(row, row.previousElementSibling);
                showAlert('Zone moved up - click "Save Order" to save', 'success');
            } else if (direction === 'down' && row.nextElementSibling) {
                tbody.insertBefore(row.nextElementSibling, row);
                showAlert('Zone moved down - click "Save Order" to save', 'success');
            }
        }

        // Mobile reorder functions for quick links
        function moveLinkRow(button, direction) {
            const row = button.closest('tr');
            const tbody = row.parentNode;
            const next = row.nextElementSibling;
            const prev = row.previousElementSibling;

            // Skip empty row
            if (direction === 'up' && prev && !prev.classList.contains('empty-row')) {
                tbody.insertBefore(row, prev);
                showAlert('Link moved up - click "Save Order" to save', 'success');
            } else if (direction === 'down' && next && !next.classList.contains('empty-row')) {
                tbody.insertBefore(next, row);
                showAlert('Link moved down - click "Save Order" to save', 'success');
            }
        }

        // ========================================
        // Quick Links Functions
        // ========================================

        function openAddLinkModal() {
            document.getElementById('add-link-form').reset();
            openModal('add-link-modal');
        }

        function openEditLinkModal(linkId) {
            const link = quickLinksData.find(l => l.id === linkId);
            if (!link) return;

            document.getElementById('edit-link-id').value = link.id;
            document.getElementById('edit-link-id-display').value = link.id;
            document.getElementById('edit-link-name').value = link.name || '';
            document.getElementById('edit-link-url').value = link.url || '';
            document.getElementById('edit-link-description').value = link.description || '';
            document.getElementById('edit-link-color').value = link.color || '#2196F3';
            document.getElementById('edit-link-enabled').checked = link.enabled !== false;
            document.getElementById('edit-link-showInNav').checked = link.showInNav !== false;
            document.getElementById('edit-link-openInNewTab').checked = link.openInNewTab === true;

            openModal('edit-link-modal');
        }

        function openDeleteLinkModal(linkId) {
            const link = quickLinksData.find(l => l.id === linkId);
            if (!link) return;

            document.getElementById('delete-link-id').value = link.id;
            document.getElementById('delete-link-name').textContent = link.name || link.id;

            openModal('delete-link-modal');
        }

        async function submitAddLinkForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'addQuickLink');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('add-link-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        async function submitEditLinkForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'updateQuickLink');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('edit-link-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        async function submitDeleteLinkForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'deleteQuickLink');

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message);
                    closeModal('delete-link-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        // Quick Links drag and drop reordering
        let draggedLinkRow = null;

        document.querySelectorAll('.link-drag').forEach(handle => {
            const row = handle.closest('tr');

            handle.addEventListener('mousedown', () => {
                row.draggable = true;
            });

            handle.addEventListener('mouseup', () => {
                row.draggable = false;
            });
        });

        const quickLinksTbody = document.getElementById('quicklinks-tbody');
        if (quickLinksTbody) {
            quickLinksTbody.addEventListener('dragstart', (e) => {
                if (e.target.tagName === 'TR') {
                    draggedLinkRow = e.target;
                    e.target.classList.add('dragging');
                }
            });

            quickLinksTbody.addEventListener('dragend', (e) => {
                if (e.target.tagName === 'TR') {
                    e.target.classList.remove('dragging');
                    e.target.draggable = false;
                    draggedLinkRow = null;
                }
            });

            quickLinksTbody.addEventListener('dragover', (e) => {
                e.preventDefault();
                const row = e.target.closest('tr');
                if (row && row !== draggedLinkRow && !row.classList.contains('empty-row')) {
                    const rect = row.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        row.parentNode.insertBefore(draggedLinkRow, row);
                    } else {
                        row.parentNode.insertBefore(draggedLinkRow, row.nextSibling);
                    }
                }
            });
        }

        async function saveLinkOrder() {
            const rows = document.querySelectorAll('#quicklinks-tbody tr:not(.empty-row)');
            const order = Array.from(rows).map(row => row.dataset.id).filter(id => id);

            const formData = new FormData();
            formData.append('action', 'reorderQuickLinks');
            formData.append('order', JSON.stringify(order));

            try {
                const response = await compatFetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Quick links order saved successfully');
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred: ' + error.message, 'error');
            }
        }

        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Close modals on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
