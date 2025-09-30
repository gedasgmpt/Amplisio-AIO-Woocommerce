<?php

namespace Amplisio\AIO\Services\Front;

use Amplisio\AIO\Repositories\OptionRepository;

use function esc_html;
use function preg_match;
use function preg_replace;
use function sanitize_hex_color;
use function sprintf;
use function str_starts_with;
use function trim;
use function wp_strip_all_tags;

class ThemeAdapter
{
    private const DEFAULT_FONT_FAMILY = '"Inter", sans-serif';
    private const DEFAULT_PRIMARY_COLOR = '#2563eb';
    private const DEFAULT_ACCENT_COLOR  = '#ec4899';
    private const DEFAULT_RADIUS        = '12px';

    private const FONT_VARIABLES = [
        '--wp--preset--font-family--body',
        '--wp--custom--font-family--body',
        '--global--font-family-base',
    ];

    private const PRIMARY_COLOR_VARIABLES = [
        '--wp--preset--color--primary',
        '--wp--custom--color--primary',
        '--global--color-primary',
    ];

    private const ACCENT_COLOR_VARIABLES = [
        '--wp--preset--color--secondary',
        '--wp--preset--color--accent',
        '--global--color-secondary',
    ];

    private const RADIUS_VARIABLES = [
        '--wp--preset--border-radius--outer',
        '--wp--custom--border-radius',
        '--global--border-radius',
    ];

    private bool $rendered = false;

    public function __construct(private OptionRepository $options)
    {
    }

    public function get_css_variables(): array
    {
        $settings = $this->options->get_theme_settings();

        return [
            '--amplisio-font-family' => $this->resolve_font_family($settings['fontFamily'] ?? ''),
            '--amplisio-primary'     => $this->resolve_color($settings['primaryColor'] ?? '', self::PRIMARY_COLOR_VARIABLES, self::DEFAULT_PRIMARY_COLOR),
            '--amplisio-accent'      => $this->resolve_color($settings['accentColor'] ?? '', self::ACCENT_COLOR_VARIABLES, self::DEFAULT_ACCENT_COLOR),
            '--amplisio-radius'      => $this->resolve_radius($settings['radius'] ?? ''),
        ];
    }

    public function get_fallbacks(): array
    {
        return [
            'fontFamily'   => self::DEFAULT_FONT_FAMILY,
            'primaryColor' => self::DEFAULT_PRIMARY_COLOR,
            'accentColor'  => self::DEFAULT_ACCENT_COLOR,
            'radius'       => self::DEFAULT_RADIUS,
        ];
    }

    public function build_style_block(string $selector = ':root'): string
    {
        $selector = trim($selector) ?: ':root';

        $variables = $this->get_css_variables();
        $declarations = [];

        foreach ($variables as $name => $value) {
            if ('' === $name || '' === $value) {
                continue;
            }

            $declarations[] = sprintf('%s: %s;', $name, $value);
        }

        if (empty($declarations)) {
            return '';
        }

        return sprintf(
            '<style id="amplisio-aio-theme-vars">%s{%s}</style>',
            esc_html($selector),
            esc_html(implode(' ', $declarations))
        );
    }

    public function output_root_variables(): void
    {
        if ($this->rendered) {
            return;
        }

        $block = $this->build_style_block(':root');
        if ('' === $block) {
            return;
        }

        echo $block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->rendered = true;
    }

    private function resolve_font_family(string $override): string
    {
        $override = $this->sanitize_font_override($override);

        if ('' !== $override) {
            return $override;
        }

        return $this->build_var_chain(self::FONT_VARIABLES, self::DEFAULT_FONT_FAMILY);
    }

    private function resolve_color(string $override, array $variables, string $fallback): string
    {
        $override = $this->sanitize_color_override($override);

        if ('' !== $override) {
            return $override;
        }

        return $this->build_var_chain($variables, $fallback);
    }

    private function resolve_radius(string $override): string
    {
        $override = $this->sanitize_radius_override($override);

        if ('' !== $override) {
            return $override;
        }

        return $this->build_var_chain(self::RADIUS_VARIABLES, self::DEFAULT_RADIUS);
    }

    private function build_var_chain(array $variables, string $fallback): string
    {
        $chain = $fallback;

        foreach (array_reverse($variables) as $variable) {
            $variable = trim($variable);

            if ('' === $variable) {
                continue;
            }

            $chain = sprintf('var(%s, %s)', $variable, $chain);
        }

        return $chain;
    }

    private function sanitize_font_override(string $value): string
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

    private function sanitize_color_override(string $value): string
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

    private function sanitize_radius_override(string $value): string
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
