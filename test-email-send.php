<?php
// Temporary test file for email sending
require_once __DIR__ . '/../../../wp-load.php';

$to = 'test@example.com'; // Test email (won't actually send, just test if wp_mail() works)
$subject = 'Test Email from YFGP Plugin';
$body = 'This is a test email to verify wp_mail() functionality.';

echo "=== Testing wp_mail() ===\n";
echo "To: $to\n";
echo "Subject: $subject\n";
echo "\nAttempting to send...\n";

$result = wp_mail($to, $subject, $body);

if ($result) {
    echo "✅ wp_mail() returned TRUE (email queued/sent)\n";
} else {
    echo "❌ wp_mail() returned FALSE (failed)\n";
}

echo "\n=== Checking for errors ===\n";
$errors = error_get_last();
if ($errors) {
    echo "Last error: " . print_r($errors, true) . "\n";
} else {
    echo "No PHP errors detected\n";
}

