<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use RuntimeException;

class Parameters
{
    private readonly array $__defs;
    private array $__data = [];

    public function __construct(array $defs)
    {
        $this->__defs = $this->normalizeDefs($defs);
    }

    private function normalizeDefs(array $defs): array
    {
        return array_map(
            static function ($def) {
                if (is_string($def)) {
                    return ['key' => $def];
                }

                return $def;
            },
            $defs
        );
    }

    protected static function mergeHandler($param, array $def)
    {
        if (!is_array($param)) {
            return $def['default'];
        }

        return array_merge($def['default'], $param);
    }

    public function bindParameters(array $params): void
    {
        $defs = $this->__defs;
        foreach ($defs as $i => $def) {
            $param = $params[$i] ?? $def['default'] ?? null;

            if (isset($def['handler'])) {
                if (is_string($def['handler'])
                && method_exists(self::class, $def['handler'] . 'Handler')) {
                    $param = call_user_func(
                        [self::class, $def['handler'] . 'Handler'],
                        $param,
                        $def
                    );
                } elseif (is_callable($def['handler'])) {
                    $param = call_user_func($def['handler'], $param, $def);
                }
            }

            $this->__data[$def['key']] = $param;
        }
    }

    /**
     * @param $key
     * @param $args
     * @return mixed
     * @throws RuntimeException
     */
    public function __call(string $key, array $args)
    {
        $cb = $this->__get($key);
        if (!is_callable($cb)) {
            throw new RuntimeException('Parameter "' . $key . '" is not callable');
        }

        return call_user_func_array($cb, $args);
    }

    public function __get(string $key): mixed
    {
        return $this->__data[$key] ?? null;
    }

    public function __set(string $key, mixed $value)
    {
        $this->__data[$key] = $value;
    }

    public function __isset(string $key)
    {
        return isset($this->__data[$key]);
    }

    public function __unset(string $key)
    {
        unset($this->__data[$key]);
    }
}
