<?php

namespace Amplisio\AIO\Services;

use Closure;
use RuntimeException;

class Container
{
    /**
     * @var array<string, Closure|object>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    public function set(string $id, Closure|object $concrete): void
    {
        $this->bindings[$id] = $concrete instanceof Closure
            ? $concrete
            : static fn (Container $container): object => $concrete;
    }

    public function singleton(string $id, Closure|object $concrete): void
    {
        $this->bindings[$id] = function (Container $container) use ($id, $concrete) {
            if ( ! isset($this->instances[$id]) ) {
                $this->instances[$id] = $concrete instanceof Closure
                    ? $concrete($container)
                    : $concrete;
            }

            return $this->instances[$id];
        };
    }

    public function get(string $id): mixed
    {
        if ( isset($this->instances[$id]) ) {
            return $this->instances[$id];
        }

        if ( ! isset($this->bindings[$id]) ) {
            throw new RuntimeException(sprintf('Service "%s" is not bound to the container.', $id));
        }

        $concrete = $this->bindings[$id];

        return $concrete($this);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
}
