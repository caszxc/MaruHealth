<?php
// includes/settings.php
// Must be included AFTER $conn is defined (i.e., after config.php)

if (!isset($conn)) {
    die("Database connection not available. Include config.php first.");
}

// Fetch all settings at once
$stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$all_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['site_name' => 'MaruHealth', ...]

// Define defaults in case DB is missing
$defaults = [
    'site_name'        => 'MaruHealth',
    'site_tagline'     => 'Your Health, Our Priority Making Quality Care More Accessible in Barangay Marulas',
    'contact_address'  => '3S Center Marulas, Market, Valenzuela, Metro Manila',
    'contact_phone'    => '0968 351 1100',
    'footer_copyright' => '© 2025 3S Barangay Marulas. All Rights Reserved.',
    'logo_path'        => 'images/site-logo.png'
];

// Merge DB values with defaults (DB overrides)
$settings = array_merge($defaults, $all_settings);

// Extract into variables (optional, for convenience)
extract($settings);

// Build cache-busted logo URL
$logo_url = $logo_path . '?v=' . (file_exists($logo_path) ? filemtime($logo_path) : time());
?>