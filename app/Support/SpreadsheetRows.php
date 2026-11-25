<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/** Reads a bounded, simple XLSX worksheet without extracting archive entries to disk. */
class SpreadsheetRows
{
    public static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('Aktifkan ekstensi PHP zip untuk membaca Excel.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File Excel tidak dapat dibuka.');

        try {
            if ($zip->numFiles > 40) throw new RuntimeException('File Excel memiliki terlalu banyak komponen.');
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $size = (int) ($stat['size'] ?? 0);
                $total += $size;
                if ($size > 5 * 1024 * 1024 || $total > 20 * 1024 * 1024) {
                    throw new RuntimeException('Ukuran hasil ekstraksi Excel melebihi batas 20 MB.');
                }
            }

            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $xml = self::parseXml($sharedXml);
                foreach ($xml->si as $item) {
                    if (count($shared) >= 50000) throw new RuntimeException('File Excel memiliki terlalu banyak teks bersama.');
                    $text = '';
                    if (isset($item->t)) $text = (string) $item->t;
                    else foreach ($item->r as $run) $text .= (string) ($run->t ?? '');
                    $shared[] = substr($text, 0, 10000);
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) throw new RuntimeException('Lembar kerja pertama tidak ditemukan.');
            $sheet = self::parseXml($sheetXml);
            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                if (count($rows) >= 5001) throw new RuntimeException('Maksimal 5.000 baris data per file Excel.');
                $cells = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $match);
                    $column = self::columnIndex($match[0] ?? 'A');
                    if ($column >= 50) throw new RuntimeException('Maksimal 50 kolom per file Excel.');
                    $type = (string) $cell['t'];
                    $value = (string) ($cell->v ?? '');
                    if ($type === 's') $value = $shared[(int) $value] ?? '';
                    elseif ($type === 'inlineStr') $value = (string) ($cell->is->t ?? '');
                    $cells[$column] = substr($value, 0, 10000);
                }
                if ($cells) {
                    ksort($cells);
                    $max = max(array_keys($cells));
                    $values = array_fill(0, $max + 1, '');
                    foreach ($cells as $index => $value) $values[$index] = $value;
                    $rows[] = $values;
                }
            }
            return $rows;
        } finally { $zip->close(); }
    }

    private static function parseXml(string $xml): \SimpleXMLElement
    {
        if (strlen($xml) > 5 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new RuntimeException('Struktur XML Excel tidak diizinkan.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if (! $parsed) throw new RuntimeException('Struktur Excel tidak valid.');
            return $parsed;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private static function columnIndex(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) $number = $number * 26 + ord($letter) - 64;
        return max(0, $number - 1);
    }
}
