<?php
/**
 * IOT Device Status API
 * Handles device status checking with proper CORS and async support
 *
 * @author Seth Morrow
 * @version 2.0.2
 * @copyright 2025
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class IOTStatusChecker
{
    private string $configFilePath;
    private array $devices;

    public function __construct()
    {
        $this->configFilePath = __DIR__ . '/DBconfigs.ini';
        $this->devices = $this->loadDevices();
    }

    /**
     * Load devices from configuration
     */
    private function loadDevices(): array
    {
        if (!file_exists($this->configFilePath) || !is_readable($this->configFilePath)) {
            return [];
        }

        // Read file content first
        $fileContent = file_get_contents($this->configFilePath);
        if ($fileContent === false) {
            return [];
        }

        // Try parse_ini_file first
        $config = parse_ini_string($fileContent, true);
        if ($config === false || empty($config)) {
            // Fallback to manual parsing
            $config = $this->parseConfigManually($fileContent);
        }

        if (empty($config)) {
            return [];
        }

        $devices = [];
        foreach ($config as $category => $categoryDevices) {
            if (!is_array($categoryDevices)) continue;
            
            foreach ($categoryDevices as $name => $address) {
                // FIXED: Ensure proper trimming here too
                $name = trim($name);
                $address = trim($address);
                
                if (empty($name) || empty($address)) continue;
                
                $devices[] = [
                    'category' => trim($category),
                    'name' => $name,
                    'address' => $address,
                    'key' => trim($category) . ":" . $name
                ];
            }
        }
        return $devices;
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
                    // FIXED: Properly trim both name and address
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

    /**
     * Ping a device
     */
    private function pingDevice(string $address): bool
    {
        // Extract IP from address (remove port and path)
        $ip = preg_replace('/:[0-9]+.*$/', '', $address);
        $ip = preg_replace('/\/.*$/', '', $ip);
        
        // Skip ping for external domains
        if (strpos($ip, '.com') !== false || strpos($ip, '.org') !== false || strpos($ip, '.net') !== false) {
            return false;
        }
        
        // Use ping command
        $pingCommand = stripos(PHP_OS, 'WIN') === 0 
            ? "ping -n 1 -w 2000 {$ip}" 
            : "ping -c 1 -W 2 {$ip}";
        
        $output = [];
        $returnVar = 0;
        exec($pingCommand . ' 2>&1', $output, $returnVar);
        
        return ($returnVar === 0);
    }

    /**
     * Check if a CURL handle indicates the device is reachable.
     * Considers any HTTP response (even 4xx/5xx) as "online" since it means
     * the device is responding. Also treats certain CURL errors as online
     * when they indicate the host accepted a TCP connection.
     */
    private function isCurlResponseOnline($ch): bool
    {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode > 0) {
            return true;
        }

        // Check if we at least established a TCP connection.
        // Some IOT devices accept connections but don't speak HTTP properly,
        // which causes CURL errors even though the device is reachable.
        $errno = curl_errno($ch);
        $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);

        // If TCP connected (connect time > 0) but transfer failed, device is up
        if ($connectTime > 0) {
            return true;
        }

        // CURLE_OPERATION_TIMEDOUT (28) after connecting means device is slow but alive
        if ($errno === CURLE_OPERATION_TIMEDOUT && $connectTime > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check status of a single device with HTTP first, ping fallback
     */
    private function checkDeviceStatus(string $address): string
    {
        // Clean up the address
        $address = trim($address);
        $address = preg_replace('/^https?:\/\//', '', $address);

        // Try HTTP GET (not HEAD - many IOT devices don't handle HEAD requests)
        $ch = curl_init("http://{$address}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'IOT-Monitor/1.0',
            CURLOPT_RANGE => '0-0',  // Only fetch first byte to minimize transfer
        ]);

        @curl_exec($ch);

        if ($this->isCurlResponseOnline($ch)) {
            curl_close($ch);
            return 'online';
        }

        curl_close($ch);

        // HTTP failed, try ping as fallback
        if ($this->pingDevice($address)) {
            return 'online';
        }

        return 'offline';
    }

    /**
     * Check status of multiple devices (batch mode)
     */
    private function checkBatchStatus(array $addresses): array
    {
        $results = [];

        // Use curl_multi for HTTP checks first
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $needsPing = [];

        foreach ($addresses as $key => $address) {
            $address = trim($address);
            $address = preg_replace('/^https?:\/\//', '', $address);

            $ch = curl_init("http://{$address}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'IOT-Monitor/1.0',
                CURLOPT_RANGE => '0-0',
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$key] = $ch;
        }

        // Execute all HTTP requests
        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        // Check HTTP results and identify devices that need ping fallback
        foreach ($curlHandles as $key => $ch) {
            if ($this->isCurlResponseOnline($ch)) {
                $results[$key] = 'online';
            } else {
                // HTTP failed, mark for ping check
                $needsPing[$key] = $addresses[$key];
                $results[$key] = 'offline'; // default to offline
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        // Now ping the devices that failed HTTP
        foreach ($needsPing as $key => $address) {
            if ($this->pingDevice($address)) {
                $results[$key] = 'online';
            }
        }

        return $results;
    }

    /**
     * Handle API requests
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            // Return all devices with their addresses
            $this->jsonResponse([
                'success' => true,
                'devices' => $this->devices,
                'timestamp' => time()
            ]);
            return;
        }
        
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? $_POST['action'] ?? '';
            
            switch ($action) {
                case 'check_single':
                    $this->handleSingleCheck($input);
                    break;
                    
                case 'check_batch':
                    $this->handleBatchCheck($input);
                    break;
                    
                case 'check_all':
                    $this->handleCheckAll();
                    break;
                    
                default:
                    $this->jsonResponse([
                        'success' => false,
                        'error' => 'Invalid action'
                    ], 400);
            }
            return;
        }
        
        $this->jsonResponse([
            'success' => false,
            'error' => 'Method not allowed'
        ], 405);
    }

    /**
     * Handle single device check
     */
    private function handleSingleCheck(array $input): void
    {
        $address = $input['address'] ?? '';
        $key = $input['key'] ?? '';
        
        if (empty($address)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Address required'
            ], 400);
            return;
        }
        
        // Reload devices to ensure we have the latest configuration
        $this->devices = $this->loadDevices();
        
        $status = $this->checkDeviceStatus($address);
        
        $this->jsonResponse([
            'success' => true,
            'key' => $key,
            'address' => $address,
            'status' => $status,
            'timestamp' => time()
        ]);
    }

    /**
     * Handle batch device check
     */
    private function handleBatchCheck(array $input): void
    {
        $devices = $input['devices'] ?? [];
        
        if (empty($devices) || !is_array($devices)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Devices array required'
            ], 400);
            return;
        }
        
        // Reload devices to ensure we have the latest configuration
        $this->devices = $this->loadDevices();
        
        $addresses = [];
        foreach ($devices as $device) {
            if (isset($device['key']) && isset($device['address'])) {
                $addresses[$device['key']] = $device['address'];
            }
        }
        
        $results = $this->checkBatchStatus($addresses);
        
        $this->jsonResponse([
            'success' => true,
            'results' => $results,
            'timestamp' => time()
        ]);
    }

    /**
     * Handle check all devices
     */
    private function handleCheckAll(): void
    {
        // Reload devices to ensure we have the latest configuration
        $this->devices = $this->loadDevices();
        
        if (empty($this->devices)) {
            $this->jsonResponse([
                'success' => true,
                'results' => [],
                'timestamp' => time()
            ]);
            return;
        }
        
        $addresses = [];
        foreach ($this->devices as $device) {
            $addresses[$device['key']] = $device['address'];
        }
        
        $results = $this->checkBatchStatus($addresses);
        
        $this->jsonResponse([
            'success' => true,
            'results' => $results,
            'count' => count($results),
            'timestamp' => time()
        ]);
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }
}

// Handle request with output buffering to prevent stray output from corrupting JSON
ob_start();
try {
    $checker = new IOTStatusChecker();
    // Discard any warnings/notices that leaked into the output buffer
    ob_end_clean();
    $checker->handleRequest();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
