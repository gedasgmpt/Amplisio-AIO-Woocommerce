<?php

namespace AmplisioAIO\Core\Helpers;

class OptionsHelper
{
    private string $option_name;

    /**
     * @var callable
     */
    private $getter;

    /**
     * @var callable
     */
    private $updater;

    /**
     * @var callable
     */
    private $deleter;

    public function __construct(
        string $option_name,
        ?callable $getter = null,
        ?callable $updater = null,
        ?callable $deleter = null
    ) {
        $this->option_name = $option_name;
        $this->getter      = $getter ?: static function ( string $option_name, $default = [] ) {
            if ( function_exists( 'get_option' ) ) {
                return get_option( $option_name, $default );
            }

            return $default;
        };
        $this->updater     = $updater ?: static function ( string $option_name, $value ) {
            if ( function_exists( 'update_option' ) ) {
                return update_option( $option_name, $value );
            }

            return true;
        };
        $this->deleter     = $deleter ?: static function ( string $option_name ) {
            if ( function_exists( 'delete_option' ) ) {
                return delete_option( $option_name );
            }

            return true;
        };
    }

    public function get_all(): array
    {
        $value = ( $this->getter )( $this->option_name, [] );

        if ( ! is_array( $value ) ) {
            return [];
        }

        return $value;
    }

    public function get( string $key, $default = null )
    {
        $all = $this->get_all();

        return $all[ $key ] ?? $default;
    }

    public function update( string $key, $value ): bool
    {
        $all          = $this->get_all();
        $all[ $key ]  = $this->sanitize_value( $value );

        return ( $this->updater )( $this->option_name, $all );
    }

    public function replace( array $values ): bool
    {
        $sanitized = [];
        foreach ( $values as $key => $value ) {
            $sanitized[ $key ] = $this->sanitize_value( $value );
        }

        return ( $this->updater )( $this->option_name, $sanitized );
    }

    public function delete( string $key ): bool
    {
        $all = $this->get_all();
        unset( $all[ $key ] );

        return ( $this->updater )( $this->option_name, $all );
    }

    public function delete_all(): bool
    {
        return ( $this->deleter )( $this->option_name );
    }

    public function merge( array $values ): bool
    {
        $all = $this->get_all();

        foreach ( $values as $key => $value ) {
            $all[ $key ] = $this->sanitize_value( $value );
        }

        return ( $this->updater )( $this->option_name, $all );
    }

    private function sanitize_value( $value )
    {
        if ( is_string( $value ) ) {
            if ( function_exists( 'sanitize_text_field' ) ) {
                return sanitize_text_field( $value );
            }

            return trim( $value );
        }

        if ( is_bool( $value ) || is_numeric( $value ) || is_array( $value ) ) {
            return $value;
        }

        return $value;
    }
}
