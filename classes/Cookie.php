<?php

namespace FSA\Neuron;

class Cookie
{
    private $options;

    public function __construct(private string $name, string $path = null, string $domain = null, bool $secure = null, bool $httponly = null, string $samesite = null)
    {
        $this->options = [
            'path' => $path ?? '/',
            'domain' => $domain ?? '',
            'secure' => $secure ?? false,
            'httponly' => $httponly ?? true,
            'samesite' => $samesite ?? 'Lax'
        ];
    }

    public function get()
    {
        return filter_input(INPUT_COOKIE, $this->name);
    }

    public function set(string $value, int $lifetime): void
    {
        $options = $this->options;
        $options['expires'] = time() + $lifetime;
        setcookie($this->name, $value, $options);
    }

    public function drop(): void
    {
        $options = $this->options;
        $options['expires'] = 1;
        setcookie($this->name, '', $options);
    }
}
