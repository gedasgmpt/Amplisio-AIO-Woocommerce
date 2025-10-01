<?php

$autoloader = __DIR__ . '/../vendor/autoload.php';

if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
} else {
    spl_autoload_register(
        static function ( string $class ): void {
            $prefix = 'AmplisioAIO\\';
            if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
                return;
            }

            $relative = substr( $class, strlen( $prefix ) );
            $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $file     = __DIR__ . '/../src/' . $relative . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        $value = is_string( $value ) ? $value : (string) $value;
        $value = strip_tags( $value );

        return trim( $value );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( string $key ): string {
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

        return $key;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( string $text ): string {
        return $text;
    }
}
