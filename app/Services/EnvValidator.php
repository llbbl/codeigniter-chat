<?php

namespace App\Services;

use Closure;

final class EnvValidator
{
    private readonly Closure $reader;

    /**
     * @param array<string, array<string, mixed>> $schema
     * @param callable(string): mixed|null        $reader
     */
    public function __construct(private readonly array $schema, ?callable $reader = null)
    {
        $this->reader = $reader !== null
            ? Closure::fromCallable($reader)
            : static fn (string $name): mixed => env($name, null);
    }

    /** @return list<string> */
    public function validate(): array
    {
        $errors = [];
        $values = [];

        foreach (array_keys($this->schema) as $name) {
            $values[$name] = ($this->reader)($name);
        }

        foreach ($this->schema as $name => $rules) {
            $value = $values[$name];
            $missing = $value === null || $value === '';
            $required = ($rules['required'] ?? false) === true || $this->isConditionallyRequired($rules, $values);

            if ($missing) {
                if ($required && ! array_key_exists('default', $rules)) {
                    $errors[] = sprintf('%s is required.', $name);
                }

                continue;
            }

            $typeError = $this->validateType($name, $value, $rules);
            if ($typeError !== null) {
                $errors[] = $typeError;

                continue;
            }

            $stringValue = (string) $value;
            if (isset($rules['min_length']) && strlen($stringValue) < $rules['min_length']) {
                $errors[] = sprintf('%s must be at least %d characters.', $name, $rules['min_length']);
            }

            $forbidden = array_map(
                static fn (mixed $candidate): string => strtolower(trim((string) $candidate)),
                $rules['forbidden_values'] ?? [],
            );
            if (in_array(strtolower(trim($stringValue)), $forbidden, true)) {
                $errors[] = sprintf('%s contains a forbidden placeholder value.', $name);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $values
     */
    private function isConditionallyRequired(array $rules, array $values): bool
    {
        if (! isset($rules['required_when']) || ! is_array($rules['required_when'])) {
            return false;
        }

        foreach ($rules['required_when'] as $name => $expected) {
            if (($values[$name] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $rules */
    private function validateType(string $name, mixed $value, array $rules): ?string
    {
        $type = $rules['type'] ?? 'string';
        $stringValue = (string) $value;

        return match ($type) {
            'bool' => $this->validateBool($name, $value),
            'int' => $this->validateInt($name, $stringValue, $rules),
            'enum' => $this->validateEnum($name, $stringValue, $rules),
            'url' => filter_var($stringValue, FILTER_VALIDATE_URL) === false
                ? sprintf('%s must be a valid URL.', $name)
                : null,
            'websocket_url' => $this->validateWebSocketUrl($name, $stringValue),
            'string' => null,
            default => sprintf('%s has unsupported schema type %s.', $name, $type),
        };
    }

    private function validateBool(string $name, mixed $value): ?string
    {
        if (is_bool($value)) {
            return null;
        }

        $allowed = ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'];

        return in_array(strtolower((string) $value), $allowed, true)
            ? null
            : sprintf('%s must be a boolean.', $name);
    }

    /** @param array<string, mixed> $rules */
    private function validateInt(string $name, string $value, array $rules): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return sprintf('%s must be an integer.', $name);
        }

        $integer = (int) $value;
        if (isset($rules['min']) && $integer < $rules['min']) {
            return sprintf('%s must be at least %d.', $name, $rules['min']);
        }
        if (isset($rules['max']) && $integer > $rules['max']) {
            return sprintf('%s must be at most %d.', $name, $rules['max']);
        }

        return null;
    }

    /** @param array<string, mixed> $rules */
    private function validateEnum(string $name, string $value, array $rules): ?string
    {
        $allowed = $rules['values'] ?? [];

        return in_array($value, $allowed, true)
            ? null
            : sprintf('%s must be one of: %s.', $name, implode(', ', $allowed));
    }

    private function validateWebSocketUrl(string $name, string $value): ?string
    {
        $parts = parse_url($value);
        if ($parts === false || ! in_array($parts['scheme'] ?? null, ['ws', 'wss'], true) || empty($parts['host'])) {
            return sprintf('%s must be a valid ws:// or wss:// URL.', $name);
        }

        return null;
    }
}
