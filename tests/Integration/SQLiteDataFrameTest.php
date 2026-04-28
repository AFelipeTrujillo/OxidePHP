<?php

declare(strict_types=1);

namespace Tabula\Tests\Integration;

use Tabula\Drivers\SQLite\SQLiteDataFrame;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the SQLite-backed DataFrame.
 *
 * These tests use the real SQLiteDataFrame implementation to verify
 * that SQL queries produce correct results.
 */
class SQLiteDataFrameTest extends TestCase
{
    private string $csvPath;

    protected function setUp(): void
    {
        // Create a temporary CSV file for testing
        $this->csvPath = tempnam(sys_get_temp_dir(), 'oxide_') . '.csv';

        $handle = fopen($this->csvPath, 'w');
        fwrite($handle, "name,age,city,salary\n");
        fwrite($handle, "Alice,30,NYC,50000\n");
        fwrite($handle, "Bob,25,LA,60000\n");
        fwrite($handle, "Charlie,35,NYC,70000\n");
        fwrite($handle, "Diana,28,LA,55000\n");
        fwrite($handle, "Eve,40,NYC,80000\n");
        fclose($handle);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->csvPath)) {
            unlink($this->csvPath);
        }
    }

    public function test_from_csv_loads_data(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->assertSame(5, $df->count());
    }

    public function test_mean(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->assertEquals(31.6, round($df->mean('age'), 1));
    }

    public function test_sum(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->assertEquals(315000, $df->sum('salary'));
    }

    public function test_min(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->assertEquals(25, $df->min('age'));
    }

    public function test_max(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->assertEquals(40, $df->max('age'));
    }

    public function test_select(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);
        $subset = $df->select(['name', 'city']);

        $expected = [
            ['name' => 'Alice', 'city' => 'NYC'],
            ['name' => 'Bob', 'city' => 'LA'],
            ['name' => 'Charlie', 'city' => 'NYC'],
            ['name' => 'Diana', 'city' => 'LA'],
            ['name' => 'Eve', 'city' => 'NYC'],
        ];

        $this->assertSame($expected, $subset->toArray());
    }

    public function test_filter(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);
        $filtered = $df->filter('city', '==', 'NYC');

        $this->assertSame(3, $filtered->count());
    }

    public function test_filter_numeric(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);
        $filtered = $df->filter('age', '>', 30);

        $this->assertSame(2, $filtered->count());
    }

    public function test_group_by_count(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);
        $result = $df->groupBy('city')->count()->toArray();

        $this->assertCount(2, $result);

        $nyc = $result[0]['city'] === 'NYC' ? $result[0] : $result[1];
        $la = $result[0]['city'] === 'LA' ? $result[0] : $result[1];

        $this->assertEquals(3, $nyc['count']);
        $this->assertEquals(2, $la['count']);
    }

    public function test_group_by_mean(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);
        $result = $df->groupBy('city')->mean('salary')->toArray();

        $nyc = $result[0]['city'] === 'NYC' ? $result[0] : $result[1];
        $la = $result[0]['city'] === 'LA' ? $result[0] : $result[1];

        // NYC: (50000 + 70000 + 80000) / 3
        $this->assertEquals(66666.67, round($nyc['salary'], 2));
        // LA: (60000 + 55000) / 2
        $this->assertEquals(57500, $la['salary']);
    }

    public function test_fluent_api_chaining(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $result = $df
            ->filter('city', '==', 'NYC')
            ->select(['name', 'age'])
            ->toArray();

        $this->assertCount(3, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Charlie', $result[1]['name']);
        $this->assertSame('Eve', $result[2]['name']);
    }

    public function test_throws_exception_for_nonexistent_column(): void
    {
        $df = SQLiteDataFrame::fromCsv($this->csvPath);

        $this->expectException(\InvalidArgumentException::class);
        $df->mean('nonexistent');
    }

    public function test_throws_exception_for_nonexistent_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SQLiteDataFrame::fromCsv('/nonexistent/file.csv');
    }

    public function test_oxide_facade(): void
    {
        $df = \Tabula\Tabula::readCsv($this->csvPath);

        $this->assertInstanceOf(SQLiteDataFrame::class, $df);
        $this->assertSame(5, $df->count());
    }

    public function test_is_ready(): void
    {
        $this->assertTrue(\Tabula\Tabula::isReady());
    }
}
