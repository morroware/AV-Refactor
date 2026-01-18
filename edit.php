<?php
declare(strict_types=1);

class IOTDeviceManager
{
    private string $configFilePath;
    private bool $canWrite;
    private array $iniArray;
    private bool $debugMode;
    private array $logMessages;

    public function __construct()
    {
        $this->configFilePath = __DIR__ . '/DBconfigs.ini';
        $this->canWrite = is_writable($this->configFilePath);
        $this->debugMode = false; // Set to true for debugging
        $this->logMessages = [];
        $this->iniArray = $this->loadConfig();
    }

    private function log(string $message, array $data = []): void
    {
        if ($this->debugMode) {
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[{$timestamp}] {$message}";
            if (!empty($data)) {
                $logEntry .= ' | Data: ' . json_encode($data);
            }
            $this->logMessages[] = $logEntry;
            error_log($logEntry);
        }
    }

    private function loadConfig(): array
    {
        try {
            if (!file_exists($this->configFilePath)) {
                $this->log('Configuration file not found', ['path' => $this->configFilePath]);
                return [];
            }

            if (!is_readable($this->configFilePath)) {
                $this->log('Configuration file not readable');
                return [];
            }

            // Read file content first
            $fileContent = file_get_contents($this->configFilePath);
            if ($fileContent === false) {
                $this->log('Failed to read configuration file');
                return [];
            }

            // Try parse_ini_string first
            $config = parse_ini_string($fileContent, true);
            if ($config === false || empty($config)) {
                $this->log('parse_ini_string failed, trying manual parsing');
                // Fallback to manual parsing
                $config = $this->parseConfigManually($fileContent);
            }

            if (empty($config)) {
                $this->log('Configuration is empty after parsing');
                return [];
            }

            // Clean up the config - trim all keys and values
            $cleanConfig = [];
            foreach ($config as $section => $values) {
                $cleanSection = trim($section);
                $cleanConfig[$cleanSection] = [];
                
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        $cleanKey = trim($key);
                        $cleanValue = trim($value);
                        if (!empty($cleanKey) && !empty($cleanValue)) {
                            $cleanConfig[$cleanSection][$cleanKey] = $cleanValue;
                        }
                    }
                }
            }

            $this->log('Configuration loaded successfully', ['sections' => count($cleanConfig)]);
            return $cleanConfig;

        } catch (Exception $e) {
            $this->log('Error loading config', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Manual INI parsing as fallback
     */
    private function parseConfigManually(string $content): array
    {
        $config = [];
        $currentSection = '';
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            
            // Check for section headers
            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $currentSection = trim($matches[1]);
                $config[$currentSection] = [];
                continue;
            }
            
            // Check for key=value pairs
            if (strpos($line, '=') !== false && !empty($currentSection)) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $name = trim(trim($parts[0]), '"\'');
                    $address = trim(trim($parts[1]), '"\'');
                    
                    if (!empty($name) && !empty($address)) {
                        $config[$currentSection][$name] = $address;
                    }
                }
            }
        }
        
        return $config;
    }

    private function saveConfig(): bool
    {
        try {
            if (!$this->canWrite) {
                $this->log('Cannot write to configuration file - no permission');
                return false;
            }

            $content = '';
            foreach ($this->iniArray as $section => $values) {
                $content .= "[" . $this->escapeIniValue($section) . "]\n";
                foreach ($values as $key => $val) {
                    $content .= $this->escapeIniValue($key) . " = " . $this->escapeIniValue($val) . "\n";
                }
                $content .= "\n";
            }

            // Create backup before saving
            if (file_exists($this->configFilePath)) {
                $backupPath = $this->configFilePath . '.backup.' . date('Y-m-d-H-i-s');
                if (!copy($this->configFilePath, $backupPath)) {
                    $this->log('Failed to create backup');
                }
            }

            $result = file_put_contents($this->configFilePath, $content, LOCK_EX) !== false;
            
            if ($result) {
                $this->log('Configuration saved successfully');
                // Reload the config after saving
                $this->iniArray = $this->loadConfig();
            } else {
                $this->log('Failed to save configuration file');
            }

            return $result;

        } catch (Exception $e) {
            $this->log('Error saving config', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function escapeIniValue(string $value): string
    {
        // Escape special characters for INI format
        if (preg_match('/[=\[\];"]/', $value) || trim($value) !== $value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    private function validateInput(string $input, string $type = 'general'): array
    {
        $input = trim($input);
        
        switch ($type) {
            case 'category':
                if (empty($input)) {
                    return ['valid' => false, 'error' => 'Category name cannot be empty'];
                }
                if (strlen($input) > 50) {
                    return ['valid' => false, 'error' => 'Category name too long (max 50 characters)'];
                }
                if (preg_match('/[\[\]"]/', $input)) {
                    return ['valid' => false, 'error' => 'Category name contains invalid characters'];
                }
                break;
                
            case 'device':
                if (empty($input)) {
                    return ['valid' => false, 'error' => 'Device name cannot be empty'];
                }
                if (strlen($input) > 100) {
                    return ['valid' => false, 'error' => 'Device name too long (max 100 characters)'];
                }
                if (preg_match('/[=\[\]"]/', $input)) {
                    return ['valid' => false, 'error' => 'Device name contains invalid characters'];
                }
                break;
                
            case 'address':
                if (empty($input)) {
                    return ['valid' => false, 'error' => 'Address cannot be empty'];
                }
                // Remove protocol if present
                $input = preg_replace('/^https?:\/\//', '', $input);
                // Basic validation for IP:port or hostname:port format
                if (!preg_match('/^[a-zA-Z0-9.-]+(?::\d+)?(?:\/.*)?$/', $input)) {
                    return ['valid' => false, 'error' => 'Invalid address format'];
                }
                break;
        }
        
        return ['valid' => true, 'value' => $input];
    }

    public function addCategory(string $categoryName): array
    {
        $validation = $this->validateInput($categoryName, 'category');
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }
        
        $categoryName = $validation['value'];
        
        if (array_key_exists($categoryName, $this->iniArray)) {
            return ['success' => false, 'message' => "Category '$categoryName' already exists"];
        }
        
        $this->iniArray[$categoryName] = [];
        
        if ($this->saveConfig()) {
            $this->log('Category added', ['category' => $categoryName]);
            return ['success' => true, 'message' => "Category '$categoryName' added successfully"];
        }
        
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    public function addDevice(string $category, string $deviceName, string $deviceAddress): array
    {
        $categoryValidation = $this->validateInput($category, 'category');
        $deviceValidation = $this->validateInput($deviceName, 'device');
        $addressValidation = $this->validateInput($deviceAddress, 'address');
        
        if (!$categoryValidation['valid']) {
            return ['success' => false, 'message' => $categoryValidation['error']];
        }
        if (!$deviceValidation['valid']) {
            return ['success' => false, 'message' => $deviceValidation['error']];
        }
        if (!$addressValidation['valid']) {
            return ['success' => false, 'message' => $addressValidation['error']];
        }
        
        $category = $categoryValidation['value'];
        $deviceName = $deviceValidation['value'];
        $deviceAddress = $addressValidation['value'];
        
        if (!array_key_exists($category, $this->iniArray)) {
            return ['success' => false, 'message' => "Category '$category' does not exist"];
        }
        
        if (array_key_exists($deviceName, $this->iniArray[$category])) {
            return ['success' => false, 'message' => "Device '$deviceName' already exists in '$category'"];
        }
        
        $this->iniArray[$category][$deviceName] = $deviceAddress;
        
        if ($this->saveConfig()) {
            $this->log('Device added', ['category' => $category, 'device' => $deviceName, 'address' => $deviceAddress]);
            return ['success' => true, 'message' => "Device '$deviceName' added to '$category'"];
        }
        
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    public function removeDevice(string $category, string $deviceName): array
    {
        if (!isset($this->iniArray[$category][$deviceName])) {
            return ['success' => false, 'message' => "Device '$deviceName' not found in '$category'"];
        }
        
        unset($this->iniArray[$category][$deviceName]);
        
        if ($this->saveConfig()) {
            $this->log('Device removed', ['category' => $category, 'device' => $deviceName]);
            return ['success' => true, 'message' => "Device '$deviceName' removed from '$category'"];
        }
        
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    public function removeCategory(string $category): array
    {
        if (!array_key_exists($category, $this->iniArray)) {
            return ['success' => false, 'message' => "Category '$category' does not exist"];
        }
        
        unset($this->iniArray[$category]);
        
        if ($this->saveConfig()) {
            $this->log('Category removed', ['category' => $category]);
            return ['success' => true, 'message' => "Category '$category' removed"];
        }
        
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    public function getDevices(): array
    {
        return $this->iniArray;
    }

    public function canWrite(): bool
    {
        return $this->canWrite;
    }

    public function getConfigPath(): string
    {
        return $this->configFilePath;
    }

    public function getDebugInfo(): array
    {
        return [
            'configPath' => $this->configFilePath,
            'canWrite' => $this->canWrite,
            'fileExists' => file_exists($this->configFilePath),
            'fileReadable' => is_readable($this->configFilePath),
            'fileWritable' => is_writable($this->configFilePath),
            'fileSize' => file_exists($this->configFilePath) ? filesize($this->configFilePath) : 0,
            'sections' => count($this->iniArray),
            'totalDevices' => array_sum(array_map('count', $this->iniArray)),
            'debugMode' => $this->debugMode,
            'logs' => $this->logMessages
        ];
    }
}

$manager = new IOTDeviceManager();
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $manager->canWrite()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            $result = $manager->addCategory($_POST['categoryName'] ?? '');
            break;
            
        case 'add_device':
            $result = $manager->addDevice(
                $_POST['deviceCategory'] ?? '',
                $_POST['deviceName'] ?? '',
                $_POST['deviceAddress'] ?? ''
            );
            break;
            
        case 'remove_device':
            $result = $manager->removeDevice(
                $_POST['category'] ?? '',
                $_POST['device'] ?? ''
            );
            break;
            
        case 'remove_category':
            $result = $manager->removeCategory($_POST['category'] ?? '');
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

$devices = $manager->getDevices();
$debugInfo = $manager->getDebugInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>IOT Device Management</title>
    <style>
        :root {
            --color-primary: #2563eb;
            --color-primary-hover: #1d4ed8;
            --color-secondary: #64748b;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --color-danger-hover: #dc2626;
            --color-background: #0f172a;
            --color-surface: #1e293b;
            --color-surface-hover: #334155;
            --color-border: #334155;
            --color-border-light: #475569;
            --color-text-primary: #f8fafc;
            --color-text-secondary: #cbd5e1;
            --color-text-muted: #64748b;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition-base: 0.2s ease;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            line-height: 1.5;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--color-background);
            color: var(--color-text-primary);
            font-weight: var(--font-weight-normal);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }

        .header {
            margin-bottom: var(--spacing-xl);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--color-surface);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border);
        }

        .header-title {
            font-size: 1.875rem;
            font-weight: var(--font-weight-bold);
            color: var(--color-text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .header-actions {
            display: flex;
            gap: var(--spacing-md);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: var(--font-weight-medium);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-base);
            user-select: none;
            min-height: 44px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background-color: var(--color-primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: var(--color-primary-hover);
        }

        .btn-secondary {
            background-color: var(--color-surface-hover);
            color: var(--color-text-primary);
            border: 1px solid var(--color-border-light);
        }

        .btn-secondary:hover:not(:disabled) {
            background-color: var(--color-border-light);
        }

        .btn-danger {
            background-color: var(--color-danger);
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            background-color: var(--color-danger-hover);
        }

        .btn-small {
            padding: var(--spacing-sm) var(--spacing-md);
            font-size: 0.875rem;
            min-height: 40px;
        }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            font-weight: var(--font-weight-medium);
            color: var(--color-text-primary);
        }

        .form-input, .form-select {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text-primary);
            font-size: 0.875rem;
            transition: border-color var(--transition-base);
            min-height: 44px;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input::placeholder {
            color: var(--color-text-muted);
        }

        .form-input:invalid {
            border-color: var(--color-danger);
        }

        .form-help {
            margin-top: var(--spacing-xs);
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--spacing-lg);
            color: var(--color-text-primary);
            padding-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .alert-info {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--color-primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .device-list {
            margin-top: var(--spacing-lg);
        }

        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-sm);
            transition: background-color var(--transition-base);
        }

        .device-item:hover {
            background-color: var(--color-surface-hover);
        }

        .device-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
            flex: 1;
        }

        .device-name {
            font-weight: var(--font-weight-medium);
            color: var(--color-text-primary);
        }

        .device-address {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
        }

        .device-address a {
            color: var(--color-primary);
            text-decoration: none;
        }

        .device-address a:hover {
            color: var(--color-primary-hover);
        }

        .device-actions {
            display: flex;
            gap: var(--spacing-sm);
        }

        .category-section {
            margin-bottom: var(--spacing-2xl);
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md);
            background-color: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
        }

        .category-title {
            font-size: 1.125rem;
            font-weight: var(--font-weight-semibold);
            color: var(--color-text-primary);
        }

        .category-meta {
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
            color: var(--color-text-muted);
            font-style: italic;
            background-color: rgba(255, 255, 255, 0.02);
            border-radius: var(--radius-md);
            border: 1px dashed var(--color-border);
        }

        .form-grid {
            display: grid;
            gap: var(--spacing-md);
        }

        .form-grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .form-grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .debug-panel {
            position: fixed;
            bottom: var(--spacing-md);
            right: var(--spacing-md);
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-sm);
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            z-index: 1000;
            max-width: 300px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .debug-panel.show {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .stat-card {
            background-color: rgba(255, 255, 255, 0.03);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            text-align: center;
            border: 1px solid var(--color-border);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            margin-top: var(--spacing-xs);
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: var(--spacing-md);
            }
            
            .header-content {
                flex-direction: column;
                gap: var(--spacing-md);
                text-align: center;
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .form-grid-2,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }
            
            .device-item {
                flex-direction: column;
                gap: var(--spacing-md);
                align-items: stretch;
            }
            
            .device-info {
                text-align: center;
            }
            
            .device-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .category-header {
                flex-direction: column;
                gap: var(--spacing-sm);
                align-items: stretch;
                text-align: center;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .device-actions {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
            
            .device-actions .btn {
                width: 100%;
            }
        }

        /* Form Validation Styles */
        .form-input.error {
            border-color: var(--color-danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-error {
            color: var(--color-danger);
            font-size: 0.75rem;
            margin-top: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        /* Confirmation Dialog */
        .confirm-dialog {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .confirm-dialog.show {
            display: flex;
        }

        .confirm-content {
            background-color: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            max-width: 400px;
            width: 90%;
            border: 1px solid var(--color-border);
        }

        .confirm-title {
            font-size: 1.125rem;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--spacing-md);
        }

        .confirm-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1 class="header-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                    IOT Device Management
                </h1>
                <div class="header-actions">
                    <a href="index.html" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"></path>
                        </svg>
                        Back to Dashboard
                    </a>
                    <button id="debug-toggle" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6M12 23v-6"></path>
                        </svg>
                        Debug
                    </button>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <?php if ($messageType === 'success'): ?>
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22,4 12,14.01 9,11.01"></polyline>
                    <?php elseif ($messageType === 'error'): ?>
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    <?php else: ?>
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    <?php endif; ?>
                </svg>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$manager->canWrite()): ?>
            <div class="alert alert-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div>
                    <strong>Warning:</strong> Cannot write to configuration file.
                    <br><code><?php echo htmlspecialchars($manager->getConfigPath()); ?></code>
                    <br>Please fix file permissions to enable editing.
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($devices); ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_map('count', $devices)); ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $manager->canWrite() ? 'Yes' : 'No'; ?></div>
                <div class="stat-label">Can Edit</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo file_exists($manager->getConfigPath()) ? 'Yes' : 'No'; ?></div>
                <div class="stat-label">Config Exists</div>
            </div>
        </div>

        <!-- Add New Category -->
        <div class="card">
            <h2 class="card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    <line x1="12" y1="11" x2="12" y2="17"></line>
                    <line x1="9" y1="14" x2="15" y2="14"></line>
                </svg>
                Add New Category
            </h2>
            <form method="POST" id="add-category-form">
                <input type="hidden" name="action" value="add_category">
                <div class="form-group">
                    <label for="categoryName" class="form-label">Category Name</label>
                    <input 
                        type="text" 
                        id="categoryName" 
                        name="categoryName" 
                        class="form-input"
                        placeholder="e.g., Temperature Sensors, WLED Controllers"
                        required
                        maxlength="50"
                        pattern="[^[\]&quot;]+"
                        title="Category name cannot contain [ ] or quote characters"
                        <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                    >
                    <div class="form-help">Category names should be descriptive and unique. Max 50 characters.</div>
                </div>
                <button type="submit" class="btn btn-primary" <?php echo $manager->canWrite() ? '' : 'disabled'; ?>>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Category
                </button>
            </form>
        </div>

        <!-- Add New Device -->
        <div class="card">
            <h2 class="card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                    <line x1="12" y1="11" x2="12" y2="11.01"></line>
                </svg>
                Add New Device
            </h2>
            <form method="POST" id="add-device-form">
                <input type="hidden" name="action" value="add_device">
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label for="deviceCategory" class="form-label">Category</label>
                        <select 
                            id="deviceCategory" 
                            name="deviceCategory" 
                            class="form-select"
                            required
                            <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                        >
                            <option value="">Select a category...</option>
                            <?php foreach (array_keys($devices) as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?> (<?php echo count($devices[$category]); ?> devices)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Choose an existing category or create a new one above.</div>
                    </div>
                    <div class="form-group">
                        <label for="deviceName" class="form-label">Device Name</label>
                        <input 
                            type="text" 
                            id="deviceName" 
                            name="deviceName" 
                            class="form-input"
                            placeholder="e.g., Kitchen Sensor, Bar WLED"
                            required
                            maxlength="100"
                            pattern="[^=[\]&quot;]+"
                            title="Device name cannot contain = [ ] or quote characters"
                            <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                        >
                        <div class="form-help">Descriptive name for the device. Max 100 characters.</div>
                    </div>
                    <div class="form-group">
                        <label for="deviceAddress" class="form-label">Device Address</label>
                        <input 
                            type="text" 
                            id="deviceAddress" 
                            name="deviceAddress" 
                            class="form-input"
                            placeholder="e.g., 192.168.1.100:5000"
                            required
                            pattern="^[a-zA-Z0-9.-]+(?::\d+)?(?:\/.*)?$"
                            title="Format: IP:port or hostname:port (e.g., 192.168.1.100:5000)"
                            <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                        >
                        <div class="form-help">IP address with optional port and path. No protocol needed.</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" <?php echo $manager->canWrite() ? '' : 'disabled'; ?>>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Device
                </button>
            </form>
        </div>

        <!-- Current Configuration -->
        <div class="card">
            <h2 class="card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
                Current Configuration
            </h2>
            <?php if (empty($devices)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 1rem; opacity: 0.5;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                        <line x1="9" y1="9" x2="9.01" y2="9"></line>
                        <line x1="15" y1="9" x2="15.01" y2="9"></line>
                    </svg>
                    <p>No devices configured yet.</p>
                    <p>Add a category and some devices to get started!</p>
                </div>
            <?php else: ?>
                <?php foreach ($devices as $categoryName => $categoryDevices): ?>
                    <div class="category-section">
                        <div class="category-header">
                            <div>
                                <div class="category-title"><?php echo htmlspecialchars($categoryName); ?></div>
                                <div class="category-meta"><?php echo count($categoryDevices); ?> device(s)</div>
                            </div>
                            <form method="POST" style="display: inline;" onsubmit="return confirmAction('remove category <?php echo htmlspecialchars($categoryName); ?>?')">
                                <input type="hidden" name="action" value="remove_category">
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryName); ?>">
                                <button 
                                    type="submit" 
                                    class="btn btn-danger btn-small"
                                    <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3,6 5,6 21,6"></polyline>
                                        <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                    Remove Category
                                </button>
                            </form>
                        </div>
                        
                        <?php if (empty($categoryDevices)): ?>
                            <div class="empty-state">
                                <p>No devices in this category yet.</p>
                                <p>Use the form above to add devices to "<?php echo htmlspecialchars($categoryName); ?>"</p>
                            </div>
                        <?php else: ?>
                            <div class="device-list">
                                <?php foreach ($categoryDevices as $deviceName => $deviceAddress): ?>
                                    <div class="device-item">
                                        <div class="device-info">
                                            <div class="device-name"><?php echo htmlspecialchars($deviceName); ?></div>
                                            <div class="device-address">
                                                <a href="http://<?php echo htmlspecialchars($deviceAddress); ?>" target="_blank" rel="noopener">
                                                    <?php echo htmlspecialchars($deviceAddress); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="device-actions">
                                            <a 
                                                href="http://<?php echo htmlspecialchars($deviceAddress); ?>" 
                                                target="_blank" 
                                                rel="noopener"
                                                class="btn btn-secondary btn-small"
                                                title="Open device in new tab"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                    <polyline points="15,3 21,3 21,9"></polyline>
                                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                                </svg>
                                                Open
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirmAction('remove device <?php echo htmlspecialchars($deviceName); ?>?')">
                                                <input type="hidden" name="action" value="remove_device">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryName); ?>">
                                                <input type="hidden" name="device" value="<?php echo htmlspecialchars($deviceName); ?>">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-danger btn-small"
                                                    <?php echo $manager->canWrite() ? '' : 'disabled'; ?>
                                                    title="Remove this device"
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3,6 5,6 21,6"></polyline>
                                                        <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"></path>
                                                    </svg>
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Debug Panel -->
    <div id="debug-panel" class="debug-panel">
        <h4>Debug Information</h4>
        <div id="debug-content">
            <strong>Config Path:</strong> <?php echo htmlspecialchars($debugInfo['configPath']); ?><br>
            <strong>Can Write:</strong> <?php echo $debugInfo['canWrite'] ? 'Yes' : 'No'; ?><br>
            <strong>File Exists:</strong> <?php echo $debugInfo['fileExists'] ? 'Yes' : 'No'; ?><br>
            <strong>File Readable:</strong> <?php echo $debugInfo['fileReadable'] ? 'Yes' : 'No'; ?><br>
            <strong>File Writable:</strong> <?php echo $debugInfo['fileWritable'] ? 'Yes' : 'No'; ?><br>
            <strong>File Size:</strong> <?php echo $debugInfo['fileSize']; ?> bytes<br>
            <strong>Sections:</strong> <?php echo $debugInfo['sections']; ?><br>
            <strong>Total Devices:</strong> <?php echo $debugInfo['totalDevices']; ?><br>
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <div id="confirm-dialog" class="confirm-dialog">
        <div class="confirm-content">
            <h3 class="confirm-title">Confirm Action</h3>
            <p id="confirm-message">Are you sure you want to proceed?</p>
            <div class="confirm-actions">
                <button id="confirm-cancel" class="btn btn-secondary">Cancel</button>
                <button id="confirm-ok" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            // Debug panel toggle
            const debugToggle = document.getElementById('debug-toggle');
            const debugPanel = document.getElementById('debug-panel');
            
            debugToggle.addEventListener('click', function() {
                debugPanel.classList.toggle('show');
            });

            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                // Skip forms that need confirmation - they're handled separately
                if (!form.getAttribute('onsubmit') || !form.getAttribute('onsubmit').includes('confirmAction')) {
                    form.addEventListener('submit', function(e) {
                        if (!validateForm(form)) {
                            e.preventDefault();
                        } else {
                            // Add loading state
                            const submitBtn = form.querySelector('button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
                            }
                        }
                    });
                }
            });

            // Real-time validation
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateInput(input);
                });
            });

            // Address formatting helper
            const addressInput = document.getElementById('deviceAddress');
            if (addressInput) {
                addressInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    // Remove protocol if user added it
                    value = value.replace(/^https?:\/\//, '');
                    this.value = value;
                });
            }

            // Handle confirmation forms
            const confirmForms = document.querySelectorAll('form[onsubmit*="confirmAction"]');
            
            confirmForms.forEach(form => {
                // Extract the message from the onsubmit attribute
                const onsubmitAttr = form.getAttribute('onsubmit');
                const messageMatch = onsubmitAttr.match(/confirmAction\('([^']+)'\)/);
                const message = messageMatch ? messageMatch[1] : 'proceed with this action';
                
                // Remove the onsubmit attribute to prevent conflicts
                form.removeAttribute('onsubmit');
                
                // Add proper event listener
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const confirmed = await showConfirmDialog(message);
                    if (confirmed) {
                        // Add loading state to submit button
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            const originalHTML = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
                        }
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });
        });

        function validateForm(form) {
            let isValid = true;
            const inputs = form.querySelectorAll('.form-input, .form-select');
            
            inputs.forEach(input => {
                if (!validateInput(input)) {
                    isValid = false;
                }
            });
            
            return isValid;
        }

        function validateInput(input) {
            const value = input.value.trim();
            let isValid = true;
            let errorMessage = '';
            
            // Clear previous errors
            input.classList.remove('error');
            const existingError = input.parentNode.querySelector('.form-error');
            if (existingError) {
                existingError.remove();
            }
            
            // Required validation
            if (input.hasAttribute('required') && !value) {
                isValid = false;
                errorMessage = 'This field is required';
            }
            
            // Pattern validation
            else if (input.hasAttribute('pattern') && value) {
                const pattern = new RegExp(input.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    isValid = false;
                    errorMessage = input.getAttribute('title') || 'Invalid format';
                }
            }
            
            // Length validation
            else if (input.hasAttribute('maxlength') && value.length > parseInt(input.getAttribute('maxlength'))) {
                isValid = false;
                errorMessage = `Maximum ${input.getAttribute('maxlength')} characters allowed`;
            }
            
            // Custom validation for device address
            else if (input.id === 'deviceAddress' && value) {
                // Check for common mistakes
                if (value.includes('http://') || value.includes('https://')) {
                    isValid = false;
                    errorMessage = 'Do not include http:// or https://';
                }
                else if (!value.includes(':') && !value.includes('/')) {
                    // Suggest adding port
                    showInputHint(input, 'Consider adding a port number (e.g., :5000)');
                }
            }
            
            if (!isValid) {
                showInputError(input, errorMessage);
            }
            
            return isValid;
        }

        function showInputError(input, message) {
            input.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.innerHTML = `
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                ${message}
            `;
            input.parentNode.appendChild(errorDiv);
        }

        function showInputHint(input, message) {
            const existingHelp = input.parentNode.querySelector('.form-help');
            if (existingHelp) {
                existingHelp.style.color = 'var(--color-warning)';
                existingHelp.textContent = message;
                setTimeout(() => {
                    existingHelp.style.color = '';
                    existingHelp.textContent = existingHelp.getAttribute('data-original') || existingHelp.textContent;
                }, 3000);
            }
        }

        // Confirmation dialog
        let pendingForm = null;

        function showConfirmDialog(message) {
            return new Promise((resolve) => {
                const dialog = document.getElementById('confirm-dialog');
                const messageEl = document.getElementById('confirm-message');
                const cancelBtn = document.getElementById('confirm-cancel');
                const okBtn = document.getElementById('confirm-ok');
                
                messageEl.textContent = `Are you sure you want to ${message}`;
                dialog.classList.add('show');
                
                function cleanup() {
                    dialog.classList.remove('show');
                    cancelBtn.removeEventListener('click', onCancel);
                    okBtn.removeEventListener('click', onOk);
                }
                
                function onCancel() {
                    cleanup();
                    resolve(false);
                }
                
                function onOk() {
                    cleanup();
                    resolve(true);
                }
                
                cancelBtn.addEventListener('click', onCancel);
                okBtn.addEventListener('click', onOk);
                
                // Close on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        onCancel();
                    }
                }, { once: true });
            });
        }

        // Auto-refresh stats periodically
        setInterval(function() {
            if (!document.hidden) {
                // Could implement AJAX refresh here if needed
            }
        }, 60000); // Every minute
    </script>
</body>
</html>
