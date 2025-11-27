<?php
// Temporary test file for settings check
require_once __DIR__ . '/../../../wp-load.php';

$settings = get_option('yfgp_settings', array());

echo "=== Email Settings Check ===\n";
echo "email_notifications: " . (isset($settings['email_notifications']) && $settings['email_notifications'] ? 'ENABLED' : 'DISABLED') . "\n";
echo "notification_email: " . ($settings['notification_email'] ?? 'NOT SET') . "\n";
echo "notify_on_success: " . (isset($settings['notify_on_success']) && $settings['notify_on_success'] ? 'YES' : 'NO') . "\n";
echo "notify_on_error: " . (isset($settings['notify_on_error']) && $settings['notify_on_error'] ? 'YES' : 'NO') . "\n";
echo "\n=== Full Settings (first 500 chars) ===\n";
echo substr(print_r($settings, true), 0, 500) . "\n";

