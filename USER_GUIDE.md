# Castle AV Control System - User Guide

This guide covers all the common features and functionality of the Castle AV Control System.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding Zones](#understanding-zones)
3. [Controlling Receivers](#controlling-receivers)
4. [Using the Remote Control](#using-the-remote-control)
5. [Channel Presets (Favorites)](#channel-presets-favorites)
6. [WLED Smart Lighting](#wled-smart-lighting)
7. [Multi-Zone Control](#multi-zone-control)
8. [Zone Management](#zone-management)
9. [Settings & Configuration](#settings--configuration)
10. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Logging In

1. Navigate to the AV Control System in your web browser
2. Enter the system password on the landing page
3. Click **Enter** or press the Enter key
4. You will be directed to the zone selection screen

**Session Duration**: Your session remains active until midnight. After that, you'll need to log in again.

### Navigating the System

After logging in, you'll see the **Zone Selection Grid** showing all available zones. Click on any zone to access its controls.

**Navigation Tips**:
- Click the **Home** button (top-left) to return to the zone selection grid
- Click the **Settings** button (top-right) to access zone configuration
- Use **Ctrl+Click** on the zone logo to quickly access settings
- Use **Ctrl+Double-Click** on the logo to return home

---

## Understanding Zones

Zones are distinct areas of the facility, each with their own AV equipment. The system includes zones such as:

| Zone | Description |
|------|-------------|
| Bowling Lanes | NeoVerse displays and bowling music system |
| Bowling Bar | Bar area TVs including billiards |
| Roller Rink | Rink video displays and audio |
| Jesters | Arcade area and bar TVs |
| Facility | Facility-wide audio control |
| Outside | Outdoor area controls |
| DJ Booth | Main entertainment hub |
| Multi | Control multiple zones at once |
| ALL | Control all zones simultaneously |

Each zone contains one or more **receivers** (output devices like TVs or speakers) that can be controlled independently.

---

## Controlling Receivers

Each receiver card in a zone provides the following controls:

### Changing Channels

1. Locate the receiver you want to control
2. Click the **Channel dropdown** menu
3. Select the desired input source (transmitter)
4. The channel change is applied automatically

**Available Input Sources**:
- Cable Box 1, 2, 3
- Apple TV
- RockBot Audio
- Wireless Mic
- Mobile Video/Audio
- Unifi Signage

### Adjusting Volume

1. Find the **Volume slider** on the receiver card
2. Drag the slider left (lower) or right (higher)
3. The volume level updates automatically after a brief delay

**Note**: Volume changes are saved after you stop adjusting (500ms delay to prevent system overload).

### Power Control

Some receivers support power on/off control:

1. Look for the **Power On** and **Power Off** buttons
2. Click **Power On** to turn the display on
3. Click **Power Off** to turn it off

**Note**: Power buttons only appear on receivers that support CEC (Consumer Electronics Control).

---

## Using the Remote Control

The virtual remote control allows you to control cable boxes just like a physical remote.

### Selecting a Transmitter

1. Use the **Transmitter dropdown** at the top of the remote section
2. Select which IR blaster/cable box to control
3. All remote button presses will be sent to that transmitter

### Remote Buttons

| Button | Function |
|--------|----------|
| **Power** | Turn cable box on/off |
| **Guide** | Open the channel guide |
| **Arrow Keys** | Navigate menus (Up/Down/Left/Right) |
| **OK/Select** | Confirm selection |
| **CH+/CH-** | Change channel up/down |
| **0-9** | Enter channel numbers directly |
| **Last** | Return to previous channel |
| **Exit** | Exit current menu |

### Entering a Channel Directly

1. Use the number pad (0-9) to enter the channel number
2. Each digit is sent with a short delay between them
3. The channel will tune after the last digit

**Example**: To tune to channel 35, press **3** then **5**.

---

## Channel Presets (Favorites)

Each zone has preset favorite channels for quick access.

### Using Favorites

1. Find the **Favorites dropdown** in the remote section
2. Click to see the list of preset channels
3. Select a channel (e.g., "ESPN", "NHL Network")
4. The system automatically sends the channel digits

### Common Preset Channels

- ESPN (Channel 35)
- ESPN2 (Channel 36)
- YES Network (Channel 70)
- SNY (Channel 60)
- NHL Network (Channel 219)
- MLB Network (Channel 213)

**Note**: Available presets vary by zone based on typical viewing preferences for that area.

---

## WLED Smart Lighting

Zones with WLED addressable lighting can be controlled directly from the interface.

### Turning Lights On/Off

1. Scroll to the **WLED Controls** section at the bottom of the zone page
2. Click **Lights On** to turn on all WLED devices in the zone
3. Click **Lights Off** to turn them all off

### Status Feedback

- A success message confirms when all devices respond
- If any devices fail to respond, you'll see which ones had issues
- Each device has a 3-second timeout

---

## Multi-Zone Control

The system provides two ways to control multiple zones at once:

### Multi Zone

The **Multi** zone allows you to select specific zones for batch operations:

1. Navigate to the **Multi** zone from the zone selection grid
2. Select which zones you want to control
3. Make your changes (channel, volume, power)
4. Changes apply to all selected zones

### ALL Zone

The **ALL** zone broadcasts commands to every receiver:

1. Navigate to the **ALL** zone
2. Any command you send applies to all receivers in the facility
3. Useful for facility-wide announcements or power management

**Use with caution**: ALL zone affects every display and speaker in the building.

---

## Zone Management

Administrators can manage zones through the Zone Manager interface.

### Accessing Zone Manager

1. Navigate to `/zonemanager.php` in your browser
2. Or access through the admin settings

### Adding a New Zone

1. Click **Add Zone**
2. Enter the zone ID (folder name, no spaces)
3. Enter the display name
4. Optionally add a description
5. Choose whether to copy configuration from an existing zone
6. Click **Create**

### Editing a Zone

1. Find the zone in the list
2. Click the **Edit** button
3. Modify the name, description, or visibility settings
4. Adjust colors if desired
5. Click **Save**

### Reordering Zones

1. Drag and drop zones in the list to change their navigation order
2. The order is saved automatically

### Deleting a Zone

1. Click the **Delete** button on the zone
2. Choose whether to also delete the zone's directory
3. Confirm the deletion

**Warning**: Deleting a zone's directory removes all its configuration files permanently.

---

## Settings & Configuration

### Accessing Zone Settings

1. Navigate to the zone you want to configure
2. Click the **Settings** button (gear icon) in the top-right
3. Or use **Ctrl+Click** on the zone logo

### Managing Receivers

**Adding a Receiver**:
1. Click **Add Receiver**
2. Enter the display name
3. Enter the IP address (must be valid format)
4. Choose whether to show power buttons
5. Click **Save**

**Removing a Receiver**:
1. Find the receiver in the list
2. Click the **Remove** button
3. Confirm the removal

### Configuring Transmitters

1. Open zone settings
2. Find the **Transmitters** section
3. Each transmitter shows its channel number mapping
4. Modify channel numbers as needed
5. Changes save automatically

### Volume Settings

Configure volume limits per zone:

| Setting | Description |
|---------|-------------|
| **Min Volume** | Lowest allowed volume level |
| **Max Volume** | Highest allowed volume level |
| **Volume Step** | Increment for each volume change |

### API Settings

| Setting | Description |
|---------|-------------|
| **API Timeout** | How long to wait for device responses (seconds) |
| **Log Level** | Amount of logging detail (error, warning, info, debug) |

### Configuration Backups

The system automatically backs up configuration before changes.

**Restoring a Backup**:
1. Open zone settings
2. Scroll to **Backups** section
3. View the list of available backups (up to 10)
4. Click **Restore** on the backup you want
5. Confirm the restoration

---

## Troubleshooting

### Receiver Shows "Loading..."

**Cause**: The system is fetching the receiver's current status.

**Solution**: Wait a few seconds. If it persists:
1. Check that the receiver is powered on
2. Verify the network connection
3. Try refreshing the page

### Channel Change Not Working

**Possible Causes**:
1. Wrong transmitter selected
2. IR blaster not responding
3. Network connectivity issue

**Solutions**:
1. Verify the correct transmitter is selected in the dropdown
2. Try the channel change again
3. Check that the cable box is responding to the remote

### Volume Slider Not Responding

**Cause**: The device may not support volume control.

**Solution**: Not all devices support volume adjustment. Check if the receiver model supports this feature.

### WLED Devices Not Responding

**Possible Causes**:
1. Device is offline
2. Network connectivity issue
3. Device IP has changed

**Solutions**:
1. Check that WLED devices are powered on
2. Verify devices are on the correct network (192.168.6.x)
3. Update device IPs in the configuration if needed

### Password Not Working

**Possible Causes**:
1. Incorrect password
2. Too many failed attempts (locks after 10)

**Solutions**:
1. Verify you're using the correct password
2. If locked out, refresh the page to reset the attempt counter

### Page Not Loading

**Solutions**:
1. Check your network connection
2. Clear browser cache
3. Try a different browser
4. Verify the server is running

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| **Ctrl+Click** on logo | Open Settings |
| **Ctrl+Double-Click** on logo | Return to Home |
| **Tab** | Navigate between controls |
| **Enter** | Activate buttons/submit forms |
| **Escape** | Close dialogs |

---

## Tips & Best Practices

1. **Use Favorites** - Preset channels are faster than entering digits manually
2. **Check the Transmitter** - Always verify the correct transmitter is selected before using the remote
3. **Allow Time for Updates** - Volume and channel changes may take a moment to apply
4. **Use Multi Zone Carefully** - Changes affect multiple areas simultaneously
5. **Backup Before Major Changes** - The system creates automatic backups, but verify before making significant configuration changes

---

## Getting Help

If you encounter issues not covered in this guide:

1. Check the system logs for error messages
2. Contact your system administrator
3. Report issues at the project repository

---

*Castle AV Control System - User Guide*
