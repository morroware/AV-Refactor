# Castle Fun Center AV Control System

A centralized, password-protected web-based audio-visual control system designed for Castle Fun Center. This system manages AV equipment across multiple entertainment zones including bowling lanes, a roller rink, arcade areas, DJ booth, and outdoor spaces. Each zone features dedicated controls for receiver management, IR remote functionality, power management, volume control, and smart lighting integration.

---

## Table of Contents

- [System Overview](#system-overview)
- [Project Structure](#project-structure)
- [Features](#features)
- [Architecture](#architecture)
- [Hardware Integration](#hardware-integration)
- [Zone Descriptions](#zone-descriptions)
- [Setup Instructions](#setup-instructions)
- [Configuration Guide](#configuration-guide)
- [Usage Guide](#usage-guide)
- [Authentication and Security](#authentication-and-security)
- [API Reference](#api-reference)
- [Alert & Monitoring System](#alert--monitoring-system)
- [Troubleshooting](#troubleshooting)
- [Maintenance](#maintenance)

---

## System Overview

The Castle Fun Center AV Control System is a production-grade web application that provides centralized control over 50+ AV devices distributed across 9 entertainment zones. The system is designed for reliability and ease of use by venue staff.

### Key Capabilities

- **Multi-Zone Control**: 9 independent zones with tailored interfaces
- **Bulk Operations**: Control all zones simultaneously or select multiple zones
- **Real-time Feedback**: Instant status updates with toast notifications and visual states
- **Smart Lighting**: WLED-compatible addressable LED control per zone
- **IR Remote Emulation**: Full cable box and media device control with number pad
- **Power Management**: CEC-based display power control
- **Anti-Popping Audio**: Intelligent audio muting during channel changes with DSP support
- **Persistent Settings**: Volume and configuration persistence across sessions
- **Quick Links**: Configurable special navigation links (Dashboard, OSD, etc.)
- **Dynamic Zone Loading**: Zones loaded from API with fallback support

### Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7.4+ with cURL |
| Frontend | HTML5, CSS3 (Material Design), jQuery 3.7.1 |
| Storage | JSON, INI, PHP constants (file-based) |
| Network | HTTP API over private 192.168.8.0/24 network |
| Lighting | WLED JSON API on 192.168.6.0/24 network |
| Fonts | Inter (Google Fonts) |

---

## Project Structure

```
AV-system/
├── index.html                 # Password-protected landing page with dynamic zone loading
├── script.js                  # Authentication, navigation logic, Ctrl+double-click handling
├── logo.png                   # Castle Fun Center branding
├── zones.json                 # Master zone configuration registry (single source of truth)
├── config.ini                 # System-wide alerts & webhooks configuration
├── .htaccess                  # Apache security configuration
│
├── zonemanager.php            # Zone management interface (add/edit/delete/duplicate/reorder)
├── settings.php               # Settings entry point (?zone=X)
├── editini.php                # Config file editor entry point (?zone=X)
├── wled.php                   # WLED control entry point (?zone=X)
│
├── api/
│   └── zones.php              # REST API endpoint for zone configuration
│
├── shared/                    # Shared codebase (core functionality)
│   ├── BaseController.php     # Base class for AJAX request handling
│   ├── utils.php              # Utility functions - API, volume, channel, DSP control
│   ├── zones.php              # Zone CRUD operations with atomic writes and caching
│   ├── settings.php           # Settings management UI with backup/restore
│   ├── editini.php            # Configuration file editor
│   ├── wled.php               # WLED control handler
│   ├── site-config.php        # Site-wide configuration
│   ├── styles.css             # Material Design dark theme with glassmorphism
│   └── script.js              # Shared JavaScript - receiver controls, remote, accessibility
│
├── zone-templates/            # Template files for new zone creation
│   ├── README.md              # Zone template instructions
│   ├── config.php             # Zone configuration template
│   ├── index.php              # Zone entry point template
│   ├── template.php           # Zone UI template
│   ├── transmitters.txt       # IR blaster device list template
│   ├── payloads.txt           # IR command codes template
│   ├── favorites.ini          # Favorite channel mappings template
│   └── WLEDlist.ini           # WLED device IP addresses template
│
└── [zone]/                    # Zone directories (9 total)
    ├── index.php              # Zone entry point (handles AJAX)
    ├── config.php             # Zone-specific configuration
    ├── template.php           # Zone UI template
    ├── transmitters.txt       # IR blaster device list
    ├── payloads.txt           # IR command codes (sendir format)
    ├── favorites.ini          # Favorite channel mappings
    ├── WLEDlist.ini           # WLED device IP addresses
    ├── saved_volumes.json     # Persistent volume state (some zones)
    └── av_controls.log        # Zone activity log

Zone Directories:
├── bowling/                   # Bowling Lanes
├── bowlingbar/                # Bowling Bar
├── rink/                      # Roller Rink
├── jesters/                   # Jesters Arcade Area
├── facility/                  # Facility-wide Controls
├── outside/                   # Outdoor Area
├── dj/                        # DJ Booth (17 receivers - largest zone)
├── multi/                     # Multi-zone Selection Control
└── all/                       # ALL Zones Simultaneous Control
```

---

## Features

### Core Features

| Feature | Description |
|---------|-------------|
| **Multi-Zone Control** | 9 independent zones with customized interfaces |
| **Receiver Management** | Channel and volume control for all AV receivers |
| **IR Remote Control** | Full cable box emulation (power, guide, navigation, numbers, channel up/down) |
| **Power Management** | CEC-based TV/display power on/off via CLI commands |
| **Volume Control** | Per-receiver volume with model-based support detection |
| **Anti-Popping Audio** | Mutes HDMI/stereo/DSP audio during channel changes to prevent pops |
| **DSP Audio Control** | Advanced audio control for 3G+AVP TX and 3G+WP4 TX devices |
| **WLED Integration** | Smart addressable LED lighting control per zone |
| **Favorite Channels** | Quick-access channel presets per zone (via INI files) |
| **Bulk Operations** | Control multiple receivers/zones simultaneously |

### Management Features

| Feature | Description |
|---------|-------------|
| **Zone Manager** | Add, edit, duplicate, reorder, and remove zones with drag-and-drop |
| **Quick Links Manager** | Add, edit, and manage special navigation links (Dashboard, OSD) |
| **Settings Editor** | Web-based receiver/transmitter configuration with validation |
| **Config File Editor** | Edit INI files (transmitters, favorites, WLED, payloads) |
| **Automatic Backups** | Configuration backups before every save (keeps 3 most recent) |
| **Backup Restoration** | Restore from any of the 10 most recent backups |
| **Atomic File Writes** | File locking with temp files prevents corruption during saves |
| **Zone Templates** | Pre-configured templates for creating new zones |
| **Configuration Caching** | In-memory caching reduces file reads |

### User Experience

| Feature | Description |
|---------|-------------|
| **Responsive Design** | Works on desktop, tablet, and mobile with touch optimization |
| **Material Design** | Dark theme with glassmorphism and gradient effects |
| **Modern UI** | Ambient glow effects, hover animations, loading spinners |
| **Session Persistence** | Authentication persists until midnight (localStorage) |
| **Real-time Feedback** | Toast notifications, visual updating states, success/error indicators |
| **Keyboard Navigation** | Full keyboard accessibility support with tabindex |
| **Screen Reader Support** | ARIA labels, live regions, and announcements |
| **Reduced Motion** | Respects `prefers-reduced-motion` media query |
| **Password Visibility Toggle** | Show/hide password with accessible button |
| **Attempt Limiting** | 10 failed password attempts locks form until refresh |
| **Dynamic Zone Loading** | Zones loaded via API with fallback to hardcoded list |
| **Ctrl+Click Shortcuts** | Ctrl+Click on logo opens settings, Ctrl+double-click returns home |

### UI Components

| Component | Description |
|-----------|-------------|
| **Navigation Grid** | 2-column responsive button grid for zone selection |
| **Receiver Cards** | Individual cards per receiver with channel/volume/power controls |
| **Virtual Remote** | Full IR remote interface with navigation pad and number pad |
| **Transmitter Selector** | Dropdown to select IR transmitter for remote commands |
| **Loading States** | Spinner animations and disabled states during operations |
| **Error Messages** | Clear error display with shake animation on failures |

---

## Architecture

### Request Flow

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Web Browser   │────▶│   index.html     │────▶│  Zone Selection │
│                 │     │  (Password Gate) │     │                 │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                                                          ▼
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  JSON Response  │◀────│   utils.php      │◀────│  /[zone]/index  │
│                 │     │  (API Calls)     │     │  (Controller)   │
└─────────────────┘     └────────┬─────────┘     └─────────────────┘
                                 │
                                 ▼
                        ┌──────────────────┐
                        │   AV Devices     │
                        │  (192.168.8.x)   │
                        └──────────────────┘
```

### Component Responsibilities

| Component | Responsibility |
|-----------|---------------|
| `index.html` | Password gate, zone navigation, dynamic zone loading from API |
| `script.js` (root) | Authentication, session management, Ctrl+click shortcuts |
| `api/zones.php` | REST API for zone configuration, validates zone directories |
| `BaseController.php` | AJAX routing, request validation, response formatting, anti-popping |
| `utils.php` | Device communication, volume/channel control, DSP audio, input validation |
| `zones.php` | Zone CRUD operations, atomic file writes, configuration caching |
| `[zone]/config.php` | Zone-specific settings (receivers, transmitters, limits) |
| `[zone]/template.php` | Zone UI rendering with receiver forms and remote control |
| `shared/script.js` | Client-side controls, accessibility, debounced volume, remote commands |

### Data Flow

1. User authenticates via password on landing page
2. Session stored in localStorage with daily expiration (midnight)
3. Zones loaded dynamically from `/api/zones.php` with timeout and fallback
4. User selects zone from navigation grid
5. Zone interface loads current device states via API calls
6. User actions trigger AJAX requests to zone controller
7. Controller validates input and calls appropriate utility functions
8. Utility functions communicate with devices via HTTP API
9. Response returned to UI with toast notifications and visual feedback

### File Locking Strategy

The system uses atomic writes with file locking to prevent data corruption:

1. Acquire exclusive lock on `.lock` file (5 second timeout)
2. Write data to temporary file (`.tmp.[pid]`)
3. Atomic rename of temp file to target file
4. Release lock and clean up

---

## Hardware Integration

### AV Receivers/Processors

The system is built specifically for **Just Add Power (JAP) 2G/3G series** AV over IP devices. These devices expose a proprietary HTTP REST API that this system uses for control.

> **Note**: This is NOT a generic "Crestron-compatible" system. While JAP devices can integrate with Crestron control systems, this application uses JAP's proprietary API. Other AV over IP devices would require code modifications to work.

**Supported JAP Models:**

| Model | Volume Support | DSP Support | Features |
|-------|---------------|-------------|----------|
| 3G+4+ TX | Yes | No | Full control |
| 3G+AVP RX | Yes | No | Full control |
| 3G+AVP TX | Yes | Yes | DSP line/HDMI audio control |
| 3G+WP4 TX | Yes | Yes | DSP line/HDMI audio control |
| 2G/3G SX | Yes | No | Basic control |

**Network**: All receivers on 192.168.8.0/24

**Adding Support for Other JAP Models:**

To add volume control support for additional JAP models, add the model string to `VOLUME_CONTROL_MODELS` in:
- `zone-templates/config.php` (for new zones)
- Each zone's `config.php` (for existing zones)

### IR Transmitters (Blasters)

Just Add Power IR blasters with HTTP API:
- Support for SENDIR format IR codes
- Support for hex format IR codes
- Multiple channels per transmitter (1-10)
- Commands executed via JAP's `fluxhandlerV2.sh` script

### Input Sources

| Source | Description |
|--------|-------------|
| Cable Box 1-3 | Cable TV receivers (Attic TX 1-3) |
| Apple TV | Streaming device |
| RockBot Audio | Background music system |
| Wireless Mic TX | Microphone transmitter |
| Mobile Video TX | Portable video source |
| Mobile Audio TX | Portable audio source |
| Unifi Signage | Digital signage system |

### Smart Lighting (WLED)

WLED-compatible addressable LED controllers:
- Network: 192.168.6.0/24
- Protocol: JSON API (`/json/state`)
- Per-zone device lists in `WLEDlist.ini`
- Bulk on/off operations
- 3-second timeout per device

---

## Zone Descriptions

| Zone | ID | Receivers | Description |
|------|-----|-----------|-------------|
| **Bowling Lanes** | `bowling` | 4 | NeoVerse displays + Bowling Music receiver |
| **Bowling Bar** | `bowlingbar` | 5 | Bar area with TV displays |
| **Roller Rink** | `rink` | - | Roller rink video/audio system |
| **Jesters** | `jesters` | - | Arcade and entertainment area |
| **Facility** | `facility` | - | Facility-wide control center |
| **Outside** | `outside` | - | Outdoor displays and audio |
| **DJ Booth** | `dj` | 17 | Main entertainment control (largest zone) |
| **Multi** | `multi` | - | Select multiple zones for batch control |
| **ALL** | `all` | - | Control all zones simultaneously |

### Special Zones

- **ALL Zone**: Sends commands to all receivers across all zones
- **Multi Zone**: Allows selecting specific zones for batch operations
- **DJ Zone**: Central control hub with 17 receivers for main event management

### Quick Links

| Link | ID | Description |
|------|-----|-------------|
| **Dashboard** | `dashboard` | System monitoring dashboard |
| **OSD** | `osd` | On-screen display controls |

---

## Setup Instructions

### Requirements

**Server:**
- PHP 7.4+ with extensions: `curl`, `json`
- Web server (Apache/Nginx) with PHP support
- Write permissions on zone directories
- POSIX functions for file ownership info (optional)

**Network:**
- Access to AV devices on 192.168.8.0/24 network
- Access to WLED devices on 192.168.6.0/24 network
- IR transmitter devices at configured IPs
- CEC-enabled displays for power control

### Installation

1. **Clone the repository:**
   ```bash
   git clone [repository_url] /var/www/html/AV-system
   cd /var/www/html/AV-system
   ```

2. **Set directory permissions:**
   ```bash
   chmod -R 755 .
   chmod -R 777 */  # Allow writes to zone directories
   chmod 666 zones.json  # Allow zone configuration updates
   ```

3. **Configure web server** (Apache example):
   ```apache
   <Directory /var/www/html/AV-system>
       AllowOverride All
       Require all granted
   </Directory>
   ```

4. **Verify network connectivity:**
   ```bash
   ping 192.168.8.25  # Test receiver connectivity
   ping 192.168.6.13  # Test WLED connectivity
   ```

### Password Configuration

The system password is defined in `/script.js`:
```javascript
const x7y9z3 = "your_password";
```

**Important**: Change this password before deploying to production.

---

## Configuration Guide

### Zone Configuration (config.php)

Each zone has a `config.php` file defining its settings:

```php
<?php
// Receivers: AV devices that accept commands
const RECEIVERS = [
    'Display Name' => [
        'ip' => '192.168.8.XX',    // Device IP address
        'show_power' => true,       // Show power on/off buttons
    ],
];

// Transmitters: Input sources mapped to channel numbers
const TRANSMITTERS = [
    'Cable Box 1' => 7,
    'Apple TV' => 2,
    'RockBot Audio' => 10,
];

// Volume settings
const MAX_VOLUME = 11;
const MIN_VOLUME = 0;
const VOLUME_STEP = 1;

// API settings
const API_TIMEOUT = 2;           // Seconds
const LOG_LEVEL = 'error';       // debug, info, warning, error

// System URLs
const HOME_URL = '/';            // Relative path for home button
const LOG_FILE = __DIR__ . '/av_controls.log';

// Remote control commands
const REMOTE_CONTROL_COMMANDS = [
    'power', 'guide', 'up', 'down', 'left', 'right', 'select',
    'channel_up', 'channel_down',
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'last', 'exit'
];

// Device models that support volume control
const VOLUME_CONTROL_MODELS = [
    '3G+4+ TX', '3G+AVP RX', '3G+AVP TX', '3G+WP4 TX', '2G/3G SX'
];
```

### Master Zone Registry (zones.json)

The `zones.json` file is the single source of truth for zone configuration:

```json
{
    "_readme": "Zone Management Configuration",
    "_instructions": {
        "adding_zone": "Add a new entry with unique 'id' (lowercase, no spaces). Set 'enabled' to true.",
        "removing_zone": "Set 'enabled' to false or remove the entry entirely.",
        "display_order": "Zones appear in the order listed here.",
        "hidden_zones": "Set 'showInNav' to false to hide from navigation."
    },
    "zones": [
        {
            "id": "bowling",
            "name": "Bowling Lanes",
            "description": "Bowling lanes area AV controls",
            "enabled": true,
            "showInNav": true,
            "icon": "bowling",
            "color": "#00C853"
        }
    ],
    "specialLinks": [
        {
            "id": "dashboard",
            "name": "Dashboard",
            "url": "dashboard/",
            "enabled": true,
            "showInNav": true,
            "color": "#2196F3",
            "openInNewTab": false
        }
    ],
    "settings": {
        "defaultColor": "#00C853",
        "allowUserZoneCreation": true,
        "requirePasswordForZoneManagement": true
    }
}
```

### Data Files

| File | Format | Purpose |
|------|--------|---------|
| `transmitters.txt` | CSV | IR blaster devices: `Name, http://IP` |
| `payloads.txt` | INI | IR commands: `command=sendir,...` |
| `favorites.ini` | INI | Quick channels: `channel_number=Channel Name` |
| `WLEDlist.ini` | INI | WLED IPs: `[WLEDs]` section with `ip1 = "192.168.X.X"` |
| `saved_volumes.json` | JSON | Persistent volume state per receiver |

### IR Payload Format

IR commands in `payloads.txt` use the SENDIR format:

```ini
power=sendir,1:1,1,58000,1,1,192,192,48,145,...
guide=0000 0048 0000 0018 00c0 00c0...
channel_up=sendir,1:1,1,58000,1,1,193,192,49,...
0=sendir,1:1,1,58000,1,1,192,192,48,145,...
```

---

## Usage Guide

### Daily Operations

1. **Access the System**: Navigate to the server address in a web browser
2. **Authenticate**: Enter the system password (10 attempts allowed)
3. **Select Zone**: Click desired zone from the navigation grid
4. **Control Devices**:
   - **Channel**: Use dropdown to select input source (auto-submits)
   - **Volume**: Adjust slider (debounced auto-save, 500ms delay)
   - **Power**: Click Power On/Off buttons
   - **Remote**: Use virtual remote for detailed control

### Zone Manager

Access via the Zone Manager link on the home page or navigate to `/zonemanager.php`:

- **Add Zone**: Create new zone with optional template copy from existing zone
- **Edit Zone**: Modify name, description, visibility, icon, and color
- **Duplicate Zone**: Clone existing zone configuration and files
- **Reorder**: Drag and drop to change navigation order
- **Delete Zone**: Remove zone (optionally delete directory and files)
- **Quick Links**: Add, edit, and manage special navigation links

### Settings Editor

Access via Ctrl+Click on zone logo or `/settings.php?zone=zonename`:

- Add/remove receivers with IP validation
- Configure transmitter mappings with channel numbers
- Set volume limits (min, max, step)
- Configure API timeout
- View/restore from configuration backups (up to 10)
- File permission and ownership information displayed

### WLED Control

Control smart lighting via `/wled.php`:

- Zone-specific lighting control
- Bulk on/off operations for all WLED devices in zone
- Per-device status tracking with failure reporting
- 3-second timeout per device with 2-second connection timeout

### Keyboard Shortcuts

| Shortcut | Location | Action |
|----------|----------|--------|
| `Ctrl+Click` | Zone logo | Open Settings Editor |
| `Ctrl+Double-Click` | Zone logo | Return to Home page |
| `Enter` | Password field | Submit password |
| `Tab` | Remote buttons | Navigate between buttons |

---

## Authentication and Security

### Authentication Model

- **Client-Side Validation**: Password verified in browser
- **Session Storage**: localStorage with daily expiration (midnight)
- **Attempt Limiting**: 10 failed attempts locks form until refresh
- **Zone Validation**: Server-side whitelist prevents unauthorized access

### Security Features

| Feature | Implementation |
|---------|---------------|
| Input Sanitization | `sanitizeInput()` validates all user input (int, ip, string types) |
| IP Validation | `filter_var()` with `FILTER_VALIDATE_IP` |
| Output Escaping | `htmlspecialchars()` prevents XSS |
| Zone Whitelist | Validation against `zones.json` registry |
| File Locking | Atomic writes with exclusive locks prevent race conditions |
| Error Masking | Internal paths not exposed to users |
| CORS Headers | API endpoints allow cross-origin requests |

### Security Recommendations

For production deployment:
- Implement server-side authentication (LDAP, SSO)
- Enable HTTPS for all connections
- Restrict access by IP address
- Move `config.ini` outside web root
- Implement rate limiting on API endpoints
- Enable PHP error logging to file (not display)
- Consider removing debug error display in production

---

## API Reference

### Device API Endpoints

Communication with AV receivers:

| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/cgi-bin/api/details/channel` | GET | Get current channel | `{"data": 2}` |
| `/cgi-bin/api/command/channel` | POST | Set channel | `{"data": "OK"}` |
| `/cgi-bin/api/details/audio/stereo/volume` | GET | Get volume | `{"data": 10}` |
| `/cgi-bin/api/command/audio/stereo/volume` | POST | Set volume | `{"data": "OK"}` |
| `/cgi-bin/api/details/device/model` | GET | Get device model | `{"data": "3G+AVP RX"}` |
| `/cgi-bin/api/command/cli` | POST | Execute CLI command | `{"data": "OK"}` |
| `/cgi-bin/api/command/audio/dsp/line` | POST | DSP line control | `{"data": "OK"}` |
| `/cgi-bin/api/command/audio/dsp/hdmi` | POST | DSP HDMI control | `{"data": "OK"}` |
| `/cgi-bin/api/command/hdmi/audio/mute` | POST | Mute HDMI audio | `{"data": "OK"}` |
| `/cgi-bin/api/command/hdmi/audio/unmute` | POST | Unmute HDMI | `{"data": "OK"}` |

### System Web APIs

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/zones.php` | GET | Get zones and quick links configuration |
| `/[zone]/` | POST | Control zone receivers (channel, volume, power, remote) |
| `/settings.php?zone=X` | GET/POST | Zone settings management |
| `/editini.php?zone=X` | GET/POST | Config file editor |
| `/wled.php` | POST | WLED lighting control |
| `/zonemanager.php` | POST | Zone management CRUD operations |

### Zone Manager API Actions

| Action | Parameters | Description |
|--------|------------|-------------|
| `add` | id, name, description, showInNav, icon, color, copyFrom | Create new zone |
| `update` | id, name, description, enabled, showInNav, icon, color | Update zone |
| `delete` | id, deleteDirectory | Remove zone |
| `duplicate` | sourceId, newId, newName | Clone zone |
| `reorder` | order (JSON array) | Reorder zones |
| `getZones` | - | Get all zones |
| `addQuickLink` | id, name, url, description, showInNav, color, openInNewTab | Add quick link |
| `updateQuickLink` | id, name, url, description, enabled, showInNav, color, openInNewTab | Update quick link |
| `deleteQuickLink` | id | Remove quick link |
| `reorderQuickLinks` | order (JSON array) | Reorder quick links |
| `getQuickLinks` | - | Get all quick links |

### WLED API

| Endpoint | Method | Payload |
|----------|--------|---------|
| `/json/state` | POST | `{"on": true}` or `{"on": false}` |

---

## Alert & Monitoring System

### Configuration (config.ini)

```ini
[general]
alerts_enabled    = "true"
alert_cooldown    = "1"          # Minutes between alerts
alert_hours_start = ""           # Optional: start hour (0-23)
alert_hours_end   = ""           # Optional: end hour (0-23)

[security]
api_key     = ""                 # API key for authentication
allowed_ips = ""                 # Comma-separated allowed IPs

[slack_bot]
bot_token         = "xoxb-..."
channel           = "#av-alerts"
dashboard_url     = "http://192.168.8.127/monitor"
alert_on_down     = "true"
alert_on_recovery = "true"

[slack]
webhook_url = ""                 # Incoming webhook URL

[custom_webhook]
url               = ""
method            = "POST"
content_type      = "json"
body_template     = ""
headers           = ""
timeout           = "5"
retry_count       = "2"
basic_auth_user   = ""
basic_auth_pass   = ""
alert_on_down     = "true"
alert_on_recovery = "true"

[email]
recipients        = "tech@castlefun.com"
from_email        = "av-system@castlefun.com"
from_name         = ""
alert_on_down     = "true"
alert_on_recovery = "true"

[textbee]
enabled           = true
api_key           = ""
device_id         = ""           # Android device ID from TextBee app
recipients        = "+1234567890"
high_priority_only = false
include_url       = true         # Include monitor URL in SMS
```

### Supported Alert Channels

| Channel | Description |
|---------|-------------|
| Slack Bot | Direct channel messages via Bot API token |
| Slack Webhook | Incoming webhook notifications |
| Email | PHP mail() function |
| TextBee SMS | SMS alerts via TextBee API (requires Android device) |
| Custom Webhook | Configurable HTTP webhooks with auth support |

---

## Troubleshooting

### Authentication Issues

| Problem | Solution |
|---------|----------|
| Locked out | Refresh page to reset attempt counter |
| Session expired | Re-enter password (expires at midnight) |
| Clear session | Delete localStorage entries for the site |

### Device Communication

| Problem | Solution |
|---------|----------|
| Connection timeout | Verify device IP in config.php, check network |
| API errors | Confirm device is powered on and accessible |
| Volume not changing | Device may not support volume (check model list) |
| Power not working | Verify `show_power` is true and device supports CEC |

### IR Commands

| Problem | Solution |
|---------|----------|
| Commands not working | Verify IPs in transmitters.txt |
| Wrong actions | Check payloads.txt has correct IR codes |
| Intermittent failures | Check network connectivity to IR blasters |
| No transmitters listed | Ensure transmitters.txt exists and is readable |

### WLED Issues

| Problem | Solution |
|---------|----------|
| Lights not responding | Verify IPs in WLEDlist.ini under `[WLEDs]` section |
| Partial control | Some devices may be offline (check failure list) |
| Timeout errors | Increase device timeout or check network |

### Configuration

| Problem | Solution |
|---------|----------|
| Settings not saving | Check write permissions on zone directories (777) |
| Backup failures | Ensure sufficient disk space |
| Config file locked | Wait for lock timeout (5 seconds) or check for stuck processes |
| Zones not loading | Check zones.json syntax and API endpoint |

### Enabling Debug Logging

In zone `config.php`:
```php
define('LOG_LEVEL', 'debug');
```

Logs written to `[zone]/av_controls.log`

---

## Maintenance

### Backup Procedures

The system automatically creates backups:
- `config_backup_YYYYMMDD_HHMMSS.php` - Before each config save
- Keeps 3 most recent backups per zone (older ones auto-deleted)
- Up to 10 backups available for restoration in Settings UI

**Manual backup:**
```bash
tar -czf av-system-backup-$(date +%Y%m%d).tar.gz /var/www/html/AV-system
```

### Log Management

Logs are stored per-zone in `av_controls.log`. To rotate:
```bash
for zone in bowling bowlingbar rink jesters facility outside dj multi all; do
    mv /var/www/html/AV-system/$zone/av_controls.log \
       /var/www/html/AV-system/$zone/av_controls.log.$(date +%Y%m%d)
done
```

### Health Checks

1. **Verify zone accessibility**: Visit each zone in browser
2. **Test device communication**: Change channel on each receiver
3. **Check WLED connectivity**: Toggle lights in each zone
4. **Review logs**: Check for error patterns
5. **Test API endpoint**: Visit `/api/zones.php` to verify JSON response

### Adding New Hardware

1. **New Receiver**:
   - Add entry to zone's `config.php` RECEIVERS array
   - Or use Settings Editor UI (`/settings.php?zone=zonename`)

2. **New Transmitter (Input Source)**:
   - Add entry to zone's `config.php` TRANSMITTERS array
   - Assign unique channel number

3. **New IR Blaster**:
   - Add entry to zone's `transmitters.txt`

4. **New WLED Device**:
   - Add entry to zone's `WLEDlist.ini` under `[WLEDs]` section

### Creating a New Zone

1. **Via Zone Manager (Recommended)**:
   - Navigate to `/zonemanager.php`
   - Click "Add Zone"
   - Optionally copy from existing zone
   - Configure receivers and transmitters via Settings

2. **Manually**:
   - Copy `zone-templates/` to new directory
   - Rename and configure all files
   - Add zone to `zones.json`

---

## Authors

- **Seth Morrow** - System architecture and development

---

## License

This project is proprietary software for Castle Fun Center AV Control System.

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 3.0 | 2025 | Complete refactor with shared codebase, zone templates, atomic file writes |
| 3.0.1 | 2025 | Added Quick Links manager, improved UI with glassmorphism, enhanced accessibility |
| 2.0 | - | Zone manager and settings UI |
| 1.0 | - | Initial multi-zone implementation |
