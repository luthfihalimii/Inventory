<?php
namespace App\Support;

final class CsvSafety
{
    public static function write($stream, array $values): void
    {
        fputcsv($stream, self::row($values));
    }

    public static function row(array $values): array
    {
        return array_map(static function ($value) {
            if ($value === null) return '';
            if (! is_scalar($value)) $value = (string) json_encode($value);
            $value = (string) $value;
            if (! is_numeric($value) && preg_match('/^[\s\x00-\x1F]*[=+@-]/', $value)) return "'".$value;
            return $value;
        }, $values);
    }
}
