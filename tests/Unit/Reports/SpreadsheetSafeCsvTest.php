<?php

namespace Tests\Unit\Reports;

use App\Support\Exports\SpreadsheetSafeCsv;
use PHPUnit\Framework\TestCase;

class SpreadsheetSafeCsvTest extends TestCase
{
    public function test_unsafe_spreadsheet_cells_are_escaped_without_changing_numeric_balances(): void
    {
        $values = [
            '=HYPERLINK("https://example.test", "open")',
            '+SUM(1,2)',
            '-2+3',
            '@SUM(1,2)',
            '   =SUM(1,2)',
            "\t=SUM(1,2)",
            "\r\n=SUM(1,2)",
            "\tUser input",
            '-42.7500',
            '42.7500',
            19,
            'Ordinary supplier',
            null,
        ];

        $this->assertSame([
            '\'=HYPERLINK("https://example.test", "open")',
            "'+SUM(1,2)",
            "'-2+3",
            "'@SUM(1,2)",
            "'   =SUM(1,2)",
            "'\t=SUM(1,2)",
            "'\r\n=SUM(1,2)",
            "'\tUser input",
            '-42.7500',
            '42.7500',
            '19',
            'Ordinary supplier',
            '',
        ], SpreadsheetSafeCsv::row($values));
    }
}
