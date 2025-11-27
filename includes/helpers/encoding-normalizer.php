<?php
/**
 * Encoding Normalization Helper
 * 
 * Normalizes strings to UTF-8 encoding.
 * Handles Cyrillic characters and works with WordPress.
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes a string to UTF-8 encoding
 * Handles Cyrillic characters and works with WordPress
 * 
 * @param string $string The string to normalize
 * @return string The normalized UTF-8 string
 */
function yfgp_normalize_utf8(string $string): string {
    // Remove any invalid multibyte characters
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    
    // Convert from other encodings if necessary
    $encodings = array('Windows-1251', 'ISO-8859-5'); // Common Cyrillic encodings
    foreach ($encodings as $encoding) {
        $test = @mb_convert_encoding($string, 'UTF-8', $encoding);
        if ($test !== false && $test !== $string) {
            $string = $test;
            break; // Found a match
        }
    }
    
    // Normalize Unicode composition (NFC)
    if (class_exists('Normalizer')) {
        if (Normalizer::isNormalized($string, Normalizer::FORM_C)) {
            return $string;
        }
        $normalized = Normalizer::normalize($string, Normalizer::FORM_C);
        if ($normalized !== false) {
            return $normalized;
        }
    }
    
    // Fallback: Remove control characters
    $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
    $string = trim($string);
    
    return $string;
}

