<?php

declare(strict_types=1);

namespace App\ProviderSetup;

use RuntimeException;

/** Read the config's literal array and env() names without executing PHP. */
final class ConfigSourceParser
{
    private array $tokens;

    private int $cursor = 0;

    public function parse(string $source): array
    {
        if (strlen($source) > 262_144) {
            throw new RuntimeException('Provider configuration source exceeds the supported bound.');
        }

        $this->tokens = array_values(array_filter(
            token_get_all($source, TOKEN_PARSE),
            fn ($token): bool => ! is_array($token) || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        $this->cursor = 0;
        $this->expect(T_RETURN);
        $result = $this->value();
        $this->expect(';');

        if ($this->cursor !== count($this->tokens) || ! is_array($result)) {
            throw new RuntimeException('Expected a single literal configuration array.');
        }

        return $result;
    }

    private function value(int $depth = 0): mixed
    {
        if ($depth > 16) {
            throw new RuntimeException('Provider configuration nesting exceeds the supported bound.');
        }

        $token = $this->tokens[$this->cursor] ?? null;

        if ($token === '[') {
            $this->cursor++;
            $values = [];

            while (($this->tokens[$this->cursor] ?? null) !== ']') {
                $value = $this->value($depth + 1);

                if ($this->isToken(T_DOUBLE_ARROW)) {
                    $this->cursor++;
                    if (! is_string($value) && ! is_int($value)) {
                        throw new RuntimeException('Unsupported provider configuration key.');
                    }
                    if (array_key_exists($value, $values)) {
                        throw new RuntimeException('Duplicate provider configuration key.');
                    }
                    $values[$value] = $this->value($depth + 1);
                } else {
                    $values[] = $value;
                }

                if (($this->tokens[$this->cursor] ?? null) === ']') {
                    break;
                }
                $this->expect(',');
            }

            $this->expect(']');

            return $values;
        }

        if ($this->isToken(T_INT_CAST)) {
            $this->cursor++;

            return $this->value($depth + 1);
        }

        if ($this->isToken(T_STRING) && strtolower($token[1]) === 'env') {
            $this->cursor++;
            $this->expect('(');
            $name = $this->value($depth + 1);
            if (! is_string($name) || preg_match('/^[A-Z][A-Z0-9_]*$/D', $name) !== 1) {
                throw new RuntimeException('Unsupported environment variable name in provider configuration.');
            }
            $default = null;
            if (($this->tokens[$this->cursor] ?? null) === ',') {
                $this->cursor++;
                $default = $this->value($depth + 1);
                if (! is_scalar($default) && $default !== null) {
                    throw new RuntimeException('Unsupported environment variable default in provider configuration.');
                }
            }
            $this->expect(')');

            return (object) ['name' => $name, 'default' => $default];
        }

        if ($this->isToken(T_CONSTANT_ENCAPSED_STRING)) {
            $this->cursor++;
            // Core uses single-quoted literals. Refuse interpolation and new
            // expression syntax instead of executing it or guessing its value.
            if (! str_starts_with($token[1], "'")) {
                throw new RuntimeException('Expected a single-quoted configuration literal.');
            }
            $literal = substr($token[1], 1, -1);

            return (string) preg_replace_callback('/\\\\([\\\\\'])/', fn (array $m): string => $m[1], $literal);
        }

        if ($this->isToken(T_LNUMBER) || $this->isToken(T_DNUMBER)) {
            $this->cursor++;
            $number = str_replace('_', '', $token[1]);
            if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $number) !== 1) {
                throw new RuntimeException('Unsupported configuration numeric literal.');
            }

            return $token[0] === T_LNUMBER ? (int) $number : (float) $number;
        }

        if ($this->isToken(T_STRING)) {
            $this->cursor++;

            return match (strtolower($token[1])) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => throw new RuntimeException('Unsupported configuration constant.'),
            };
        }

        throw new RuntimeException('Unsupported expression in provider configuration; review the installed package.');
    }

    private function isToken(int $kind): bool
    {
        $token = $this->tokens[$this->cursor] ?? null;

        return is_array($token) && $token[0] === $kind;
    }

    private function expect(int|string $expected): void
    {
        if (is_int($expected) ? ! $this->isToken($expected) : ($this->tokens[$this->cursor] ?? null) !== $expected) {
            throw new RuntimeException('Unsupported provider configuration syntax.');
        }
        $this->cursor++;
    }
}
