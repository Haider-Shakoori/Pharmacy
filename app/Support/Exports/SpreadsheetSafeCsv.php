<?php

namespace App\Support\Exports;

final class SpreadsheetSafeCsv
{
    /**
     * Prevent spreadsheet software from evaluating attacker-controlled CSV cells.
     * Plain negative decimal values remain numeric for financial exports.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    public static function row(array $values): array
    {
        return array_map(self::cell(...), $values);
    }

    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if (preg_match('/^-[0-9]+(?:\.[0-9]+)?$/D', $text) === 1) {
            return $text;
        }

        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $text) === 1
            || preg_match('/^[\t\r\n]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
