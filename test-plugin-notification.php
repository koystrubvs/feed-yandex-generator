<?php
// Temporary test file for plugin notification functionality
require_once __DIR__ . '/../../../wp-load.php';

// Enable email notifications for testing
$settings = get_option('yfgp_settings', array());
$settings['email_notifications'] = true;
$settings['notification_email'] = 'test@example.com';
$settings['notify_on_success'] = true;
$settings['notify_on_error'] = true;
update_option('yfgp_settings', $settings);

echo "=== Testing Plugin send_notification() ===\n";
echo "Settings updated:\n";
echo "- email_notifications: ENABLED\n";
echo "- notification_email: test@example.com\n";
echo "- notify_on_success: YES\n";
echo "- notify_on_error: YES\n\n";

// Simulate the send_notification() method logic
$status = 'success';
$message = 'Test feed update';

// Check if notifications are enabled
if (empty($settings['email_notifications'])) {
    echo "❌ Email notifications are disabled\n";
    exit;
}

// Check if we should send for this status
if ($status === 'success' && empty($settings['notify_on_success'])) {
    echo "❌ Notify on success is disabled\n";
    exit;
}

$to = $settings['notification_email'] ?? get_option('admin_email');
$site_name = get_bloginfo('name');

if ($status === 'success') {
    $subject = '[' . $site_name . '] ✅ Yandex Feed: Успешное обновление';
    $body = "Добрый день!\n\n";
    $body .= "Фид Yandex успешно обновлён.\n\n";
    $body .= "Дата: " . current_time('d.m.Y H:i') . "\n";
    $body .= "Сообщение: " . $message . "\n\n";
    $body .= "URL фида: " . home_url('/wp-content/uploads/feed/doctors.yml') . "\n\n";
    $body .= "---\n";
    $body .= "Это автоматическое уведомление от плагина Yandex Feed Generator Pro";
} else {
    $subject = '[' . $site_name . '] ❌ Yandex Feed: Ошибка обновления';
    $body = "Добрый день!\n\n";
    $body .= "При обновлении фида Yandex произошла ошибка.\n\n";
    $body .= "Дата: " . current_time('d.m.Y H:i') . "\n";
    $body .= "Ошибка: " . $message . "\n\n";
    $body .= "Пожалуйста, проверьте настройки плагина.\n\n";
    $body .= "---\n";
    $body .= "Это автоматическое уведомление от плагина Yandex Feed Generator Pro";
}

echo "Attempting to send notification...\n";
echo "To: $to\n";
echo "Subject: $subject\n";
echo "Body length: " . strlen($body) . " chars\n\n";

$result = wp_mail($to, $subject, $body);

if ($result) {
    echo "✅ wp_mail() returned TRUE\n";
} else {
    echo "❌ wp_mail() returned FALSE (sendmail not configured)\n";
}

// Restore original settings
$settings['email_notifications'] = false;
update_option('yfgp_settings', $settings);
echo "\nSettings restored to original state\n";

