<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;

/** Tiny rule-based validator with custom rules and dotted field access. */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, array{0: callable, 1: ?string}> */
    private array $custom = [];

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

    /** Register a rule: fn (mixed $value, ?string $parameter, array $data): bool */
    public function extend(string $rule, callable $callback, ?string $message = null): self
    {
        $this->custom[$rule] = [$callback, $message];

        return $this;
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
            if (!isset($this->errors[$field]) && $this->hasField((string) $field)) {
                $validated[$field] = $this->valueFor((string) $field);
            }
        }

        return $validated;
    }

    private function validateField(string $field, string|array $rules): void
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $value = $this->valueFor($field);

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
        if (isset($this->custom[$rule])) {
            return (bool) ($this->custom[$rule][0])($value, $parameter, $this->data);
        }

        return match ($rule) {
            'required' => $value !== null && $value !== '' && $value !== [],
            'nullable' => true,
            'string' => is_string($value),
            'integer' => is_int($value) || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'boolean' => is_bool($value) || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'uuid' => is_string($value) && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $value,
            ) === 1,
            'json' => is_string($value) && json_decode($value, true) !== null,
            'alpha' => is_string($value) && $value !== '' && ctype_alpha($value),
            'alpha_num' => is_string($value) && $value !== '' && ctype_alnum($value),
            'digits' => is_string($value) && ctype_digit($value) && strlen($value) === (int) $parameter,
            'min' => $this->min($value, $parameter),
            'max' => $this->max($value, $parameter),
            'between' => $this->between($value, $parameter),
            'in' => in_array((string) $value, explode(',', (string) $parameter), true),
            'regex' => $this->regex($value, $parameter),
            'confirmed' => $this->valueFor($field . '_confirmation') === $value,
            'same' => $this->valueFor((string) $parameter) === $value,
            'different' => $this->valueFor((string) $parameter) !== $value,
            'required_if' => $this->requiredIf($parameter, $value),
            default => throw new InvalidArgumentException("Unknown validation rule [{$rule}]."),
        };
    }

    private function hasField(string $field): bool
    {
        $value = $this->data;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    private function valueFor(string $field): mixed
    {
        $value = $this->data;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
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

    private function between(mixed $value, ?string $parameter): bool
    {
        [$min, $max] = array_pad(explode(',', (string) $parameter, 2), 2, '');

        return $this->min($value, $min) && $this->max($value, $max);
    }

    private function requiredIf(?string $parameter, mixed $value): bool
    {
        $parts = explode(',', (string) $parameter, 2);
        $other = $this->valueFor($parts[0]);
        $expected = $parts[1] ?? '';

        if ((string) $other !== $expected) {
            return true;
        }

        return $value !== null && $value !== '' && $value !== [];
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

        if (isset($this->custom[$rule][1])) {
            return (string) $this->custom[$rule][1];
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
