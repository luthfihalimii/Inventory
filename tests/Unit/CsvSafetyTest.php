<?php

namespace Tests\Unit;

use App\Support\CsvSafety;
use PHPUnit\Framework\TestCase;

class CsvSafetyTest extends TestCase
{
    public function test_it_prefixes_spreadsheet_formulas_but_keeps_numeric_values(): void
    {
        $row = CsvSafety::row(['=HYPERLINK("https://bad")', '+CMD', '-10', 125000, '@SUM(A1:A2)', 'Normal']);
        $this->assertSame("'=HYPERLINK(\"https://bad\")", $row[0]);
        $this->assertSame("'+CMD", $row[1]);
        $this->assertSame('-10', $row[2]);
        $this->assertSame(125000, $row[3]);
        $this->assertSame("'@SUM(A1:A2)", $row[4]);
        $this->assertSame('Normal', $row[5]);
    }
}
