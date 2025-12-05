<?php
/**
 * DotEnv - Simple .env loader for PHP 8.3+
 * Compatible with shared hosting environments where putenv() may be disabled.
 * 
 * @package WhiteLabel\Core
 * @author  Daruma Consulting
 * @version 1.0.0
 */

declare(strict_types=1);

class DotEnv
{
    protected string $path;
    protected bool $usePutenv;

    /**
     * @param string $path Path to .env file
     * @throws RuntimeException if file not found
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Environment file not found: {$path}");
        }
        
        $this->path = $path;
        // Check if putenv is available (some hosts disable it)
        $this->usePutenv = function_exists('putenv') && !in_array('putenv', explode(',', ini_get('disable_functions')));
    }

    /**
     * Load environment variables from .env file
     */
    public function load(): void
    {
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            // Skip comments
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = $this->parseValue(trim($value));

                // Set in $_ENV superglobal
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                }

                // Also set in $_SERVER for compatibility
                if (!array_key_exists($name, $_SERVER)) {
                    $_SERVER[$name] = $value;
                }

                // Use putenv if available
                if ($this->usePutenv) {
                    putenv("{$name}={$value}");
                }
            }
        }
    }

    /**
     * Parse value, removing quotes and handling special values
     */
    protected function parseValue(string $value): string
    {
        // Remove surrounding quotes
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        // Handle special values
        return match (strtolower($value)) {
            'true', '(true)'   => '1',
            'false', '(false)' => '0',
            'null', '(null)'   => '',
            'empty', '(empty)' => '',
            default            => $value
        };
    }
}
