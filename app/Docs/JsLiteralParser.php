<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Converts a JavaScript object/array literal (as found in VitePress config
 * files and Vue script-setup blocks) into a PHP array. Handles unquoted keys,
 * single-quoted strings, trailing commas, and line comments — enough for the
 * literals the prism docs ship, not a general JS parser.
 */
class JsLiteralParser
{
    public static function parse(string $js): ?array
    {
        $out = '';
        $length = strlen($js);
        $inString = false;
        $quote = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $js[$i];

            if ($inString) {
                if ($char === '\\') {
                    $next = $js[$i + 1] ?? '';
                    if ($quote === "'" && $next === "'") {
                        $out .= "'";
                        $i++;

                        continue;
                    }
                    $out .= $char.$next;
                    $i++;

                    continue;
                }

                if ($char === $quote) {
                    $inString = false;
                    $out .= '"';

                    continue;
                }

                if ($char === '"' && $quote === "'") {
                    $out .= '\\"';

                    continue;
                }

                $out .= $char;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                $out .= '"';

                continue;
            }

            if ($char === '/' && ($js[$i + 1] ?? '') === '/') {
                while ($i < $length && $js[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";

                continue;
            }

            $out .= $char;
        }

        // Quote unquoted object keys.
        $out = (string) preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $out);

        // Quote bare identifier values (JS constants/enums) — but not JSON literals.
        $out = (string) preg_replace_callback(
            '/:\s*([A-Za-z_][A-Za-z0-9_]*)\s*(?=[,}\]\n])/',
            fn (array $m): string => in_array($m[1], ['true', 'false', 'null'], true)
                ? ': '.$m[1]
                : ': "'.$m[1].'"',
            $out
        );

        // Remove trailing commas.
        $out = (string) preg_replace('/,(\s*[}\]])/', '$1', $out);

        $decoded = json_decode($out, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Extract a balanced JS array literal that follows `<name>: [` or
     * `const <name> = [` in the given source.
     */
    public static function extractArray(string $source, string $name): ?string
    {
        if (preg_match('/(?:'.preg_quote($name, '/').')\s*[:=]\s*\[/s', $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]) - 1;
        $depth = 0;
        $inString = false;
        $quote = '';

        for ($i = $start, $len = strlen($source); $i < $len; $i++) {
            $char = $source[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;

                continue;
            }

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
