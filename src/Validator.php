<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;

/** Tiny rule-based validator. No database or locale dependencies. */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $data @param array<string, string|list<string>> $rules */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $rules) {
            $this->validateField((string) $field, $rules);
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $this->passes();

        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        $this->passes();
        $validated = [];

        foreach ($this->rules as $field => $rules) {
            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    private function validateField(string $field, string|array $rules): void
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $value = $this->data[$field] ?? null;

        foreach ($rules as $rule) {
            [$name, $parameter] = $this->parseRule((string) $rule);
            if ($name === 'nullable' && $value === null) {
                return;
            }

            if (!$this->check($name, $parameter, $value, $field)) {
                $this->errors[$field][] = $this->message($field, $name, $parameter);
            }
        }
    }

    /** @return array{0: string, 1: ?string} */
    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private function check(string $rule, ?string $parameter, mixed $value, string $field): bool
    {
        return match ($rule) {
            'required' => $value !== null && $value !== '' && $value !== [],
            'string' => is_string($value),
            'integer' => is_int($value) || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'boolean' => is_bool($value) || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'min' => $this->min($value, $parameter),
            'max' => $this->max($value, $parameter),
            'in' => in_array((string) $value, explode(',', (string) $parameter), true),
            'regex' => $this->regex($value, $parameter),
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
            'same' => ($this->data[(string) $parameter] ?? null) === $value,
            default => throw new InvalidArgumentException("Unknown validation rule [{$rule}]."),
        };
    }

    private function min(mixed $value, ?string $parameter): bool
    {
        $min = (int) $parameter;
        if (is_array($value)) {
            return count($value) >= $min;
        }
        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        return is_numeric($value) && $value >= $min;
    }

    private function max(mixed $value, ?string $parameter): bool
    {
        $max = (int) $parameter;
        if (is_array($value)) {
            return count($value) <= $max;
        }
        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        return is_numeric($value) && $value <= $max;
    }

    private function regex(mixed $value, ?string $parameter): bool
    {
        if (!is_string($value) || $parameter === null) {
            return false;
        }

        $pattern = str_starts_with($parameter, '/') || str_starts_with($parameter, '~')
            ? $parameter
            : '~' . $parameter . '~';

        return @preg_match($pattern, $value) === 1;
    }

    private function message(string $field, string $rule, ?string $parameter): string
    {
        $key = $field . '.' . $rule;
        if (isset($this->messages[$key])) {
            return (string) $this->messages[$key];
        }

        return match ($rule) {
            'required' => "The {$field} field is required.",
            'min' => "The {$field} field must be at least {$parameter}.",
            'max' => "The {$field} field may not be greater than {$parameter}.",
            'in' => "The selected {$field} is invalid.",
            'email' => "The {$field} field must be a valid email address.",
            'confirmed' => "The {$field} confirmation does not match.",
            default => "The {$field} field failed the {$rule} validation.",
        };
    }
}
