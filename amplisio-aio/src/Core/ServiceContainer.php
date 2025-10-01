<?php

namespace AmplisioAIO\Core;

use InvalidArgumentException;

class ServiceContainer
{
    /**
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    public function set( string $id, callable $factory, bool $shared = true ): void
    {
        $this->factories[ $id ] = static function () use ( $factory, $shared ) {
            $value = $factory();

            return [ 'value' => $value, 'shared' => $shared ];
        };
    }

    public function singleton( string $id, callable $factory ): void
    {
        $this->set(
            $id,
            function () use ( $id, $factory ) {
                if ( ! array_key_exists( $id, $this->instances ) ) {
                    $this->instances[ $id ] = $factory();
                }

                return $this->instances[ $id ];
            },
            true
        );
    }

    public function factory( string $id, callable $factory ): void
    {
        $this->set( $id, $factory, false );
    }

    public function has( string $id ): bool
    {
        return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
    }

    public function get( string $id )
    {
        if ( isset( $this->instances[ $id ] ) ) {
            return $this->instances[ $id ];
        }

        if ( ! isset( $this->factories[ $id ] ) ) {
            throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered.', $id ) );
        }

        $factory = $this->factories[ $id ];
        $result  = $factory();

        if ( is_array( $result ) && array_key_exists( 'shared', $result ) ) {
            if ( $result['shared'] ) {
                $this->instances[ $id ] = $result['value'];

                return $this->instances[ $id ];
            }

            return $result['value'];
        }

        return $result;
    }
}
