<?php

namespace AmplisioAIO\Core\Helpers;

use RuntimeException;

class AssetHelper
{
    private string $manifest_path;

    private string $asset_url;

    private string $asset_dir;

    private ?array $manifest = null;

    public function __construct( string $manifest_path, string $asset_url )
    {
        $this->manifest_path = $manifest_path;
        $this->asset_url     = rtrim( $asset_url, '/' ) . '/';
        $this->asset_dir     = dirname( $manifest_path ) . '/';
    }

    public function enqueue( string $entry, string $handle, array $deps = [], bool $in_footer = true ): void
    {
        $asset = $this->get_entry( $entry );

        if ( isset( $asset['css'] ) ) {
            $style_handle = $handle . '-style';
            wp_register_style(
                $style_handle,
                $this->asset_url . $asset['css'],
                [],
                $this->asset_version( $asset['css'] )
            );
            wp_enqueue_style( $style_handle );
        }

        if ( isset( $asset['js'] ) ) {
            wp_register_script(
                $handle,
                $this->asset_url . $asset['js'],
                $deps,
                $this->asset_version( $asset['js'] ),
                $in_footer
            );
            wp_enqueue_script( $handle );
        }
    }

    public function get_entry( string $entry ): array
    {
        $manifest = $this->load_manifest();

        if ( ! isset( $manifest[ $entry ] ) ) {
            throw new RuntimeException( sprintf( 'Asset entry "%s" is not defined in manifest.', $entry ) );
        }

        return $manifest[ $entry ];
    }

    private function load_manifest(): array
    {
        if ( null !== $this->manifest ) {
            return $this->manifest;
        }

        if ( ! file_exists( $this->manifest_path ) ) {
            return $this->manifest = [];
        }

        $decoded = json_decode( (string) file_get_contents( $this->manifest_path ), true );

        if ( ! is_array( $decoded ) ) {
            return $this->manifest = [];
        }

        return $this->manifest = $decoded;
    }

    private function asset_version( string $relative ): string
    {
        $path = $this->asset_dir . $relative;

        if ( file_exists( $path ) ) {
            return (string) filemtime( $path );
        }

        return AMPLISIO_AIO_VERSION;
    }
}
