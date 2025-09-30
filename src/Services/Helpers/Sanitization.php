<?php

namespace Amplisio\AIO\Services\Helpers;

use function preg_match;
use function preg_replace;
use function sanitize_hex_color;
use function str_starts_with;
use function trim;
use function wp_strip_all_tags;

class Sanitization
{
    public static function sanitize_theme_settings(array $settings): array
    {
        return [
            'fontFamily'   => self::sanitize_font_family($settings['fontFamily'] ?? ''),
            'primaryColor' => self::sanitize_color($settings['primaryColor'] ?? ''),
            'accentColor'  => self::sanitize_color($settings['accentColor'] ?? ''),
            'radius'       => self::sanitize_radius($settings['radius'] ?? ''),
        ];
    }

    private static function sanitize_font_family(string $value): string
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (str_starts_with($value, 'var(') && preg_match('/^var\(--[a-z0-9_-]+(?:,\s*[^\)]+)?\)$/i', $value)) {
            return $value;
        }

        $value = wp_strip_all_tags($value);
        $value = preg_replace("/[^\\w\\s,\"'-]/u", '', $value);

        return trim($value);
    }

    private static function sanitize_color(string $value): string
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (str_starts_with($value, 'var(') && preg_match('/^var\(--[a-z0-9_-]+(?:,\s*[^\)]+)?\)$/i', $value)) {
            return $value;
        }

        $hex = sanitize_hex_color($value);

        return $hex ?: '';
    }

    private static function sanitize_radius(string $value): string
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (str_starts_with($value, 'var(') && preg_match('/^var\(--[a-z0-9_-]+(?:,\s*[^\)]+)?\)$/i', $value)) {
            return $value;
        }

        if ('0' === $value) {
            return '0';
        }

        if (preg_match('/^\d+(?:\.\d+)?(px|rem|em|%)$/', $value)) {
            return $value;
        }

        return '';
    }
}
