<?php

declare(strict_types=1);

namespace Oxide\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the DataFrame interface contract.
 *
 * These tests validate the expected behavior of any DataFrame implementation.
 * We use an ArrayDataFrame as a lightweight test double that simulates
 * the interface contract without requiring any external engine.
 */
class DataFrameTest extends TestCase
{
    // -----------------------------------------------------------------------
    //  fromCsv()
    // -----------------------------------------------------------------------

    public function test_from_csv_throws_exception_when_file_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ArrayDataFrame::fromCsv('/nonexistent/file.csv');
    }

    // -----------------------------------------------------------------------
    //  count()
    // -----------------------------------------------------------------------

    public function test_count_returns_zero_for_empty_dataframe(): void
    {
        $df = new ArrayDataFrame([]);

        $this->assertSame(0, $df->count());
    }

    public function test_count_returns_number_of_rows(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertSame(3, $df->count());
    }

    // -----------------------------------------------------------------------
    //  mean()
    // -----------------------------------------------------------------------

    public function test_mean_computes_average_of_column(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
            ['name' => 'Charlie', 'age' => 40],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertEquals(30.0, $df->mean('age'));
    }

    public function test_mean_throws_exception_for_nonexistent_column(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $df->mean('nonexistent');
    }

    public function test_mean_throws_exception_for_non_numeric_column(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-numeric');

        $df->mean('name');
    }

    public function test_mean_returns_zero_when_no_rows(): void
    {
        $df = new ArrayDataFrame([], ['age']);

        $this->assertEquals(0.0, $df->mean('age'));
    }

    // -----------------------------------------------------------------------
    //  sum()
    // -----------------------------------------------------------------------

    public function test_sum_computes_total_of_column(): void
    {
        $df = new ArrayDataFrame([
            ['value' => 10],
            ['value' => 20],
            ['value' => 30],
        ]);

        $this->assertEquals(60.0, $df->sum('value'));
    }

    public function test_sum_throws_exception_for_non_numeric_column(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-numeric');

        $df->sum('name');
    }

    // -----------------------------------------------------------------------
    //  min() / max()
    // -----------------------------------------------------------------------

    public function test_min_returns_smallest_value(): void
    {
        $df = new ArrayDataFrame([
            ['age' => 30],
            ['age' => 25],
            ['age' => 35],
        ]);

        $this->assertEquals(25, $df->min('age'));
    }

    public function test_max_returns_largest_value(): void
    {
        $df = new ArrayDataFrame([
            ['age' => 30],
            ['age' => 25],
            ['age' => 35],
        ]);

        $this->assertEquals(35, $df->max('age'));
    }

    public function test_min_throws_exception_for_nonexistent_column(): void
    {
        $df = new ArrayDataFrame([['age' => 30]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $df->min('nonexistent');
    }

    // -----------------------------------------------------------------------
    //  select()
    // -----------------------------------------------------------------------

    public function test_select_returns_only_requested_columns(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
        ];
        $df = new ArrayDataFrame($data);

        $subset = $df->select(['name', 'city']);

        $expected = [
            ['name' => 'Alice', 'city' => 'NYC'],
            ['name' => 'Bob', 'city' => 'LA'],
        ];
        $this->assertSame($expected, $subset->toArray());
    }

    // -----------------------------------------------------------------------
    //  filter()
    // -----------------------------------------------------------------------

    public function test_filter_with_equal_operator(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 30],
        ];
        $df = new ArrayDataFrame($data);

        $filtered = $df->filter('age', '==', 30);

        $this->assertSame(2, $filtered->count());
        $this->assertSame('Alice', $filtered->toArray()[0]['name']);
        $this->assertSame('Charlie', $filtered->toArray()[1]['name']);
    }

    public function test_filter_with_greater_than_operator(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ];
        $df = new ArrayDataFrame($data);

        $filtered = $df->filter('age', '>', 25);

        $this->assertSame(2, $filtered->count());
    }

    public function test_filter_with_less_than_operator(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $filtered = $df->filter('age', '<', 30);
        $this->assertSame(1, $filtered->count());
        $this->assertSame('Bob', $filtered->toArray()[0]['name']);
    }

    public function test_filter_returns_empty_when_no_match(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $filtered = $df->filter('age', '>', 100);
        $this->assertSame(0, $filtered->count());
    }

    // -----------------------------------------------------------------------
    //  groupBy()
    // -----------------------------------------------------------------------

    public function test_group_by_count(): void
    {
        $df = new ArrayDataFrame([
            ['city' => 'NYC', 'name' => 'Alice'],
            ['city' => 'LA', 'name' => 'Bob'],
            ['city' => 'NYC', 'name' => 'Charlie'],
        ]);

        $result = $df->groupBy('city')->count()->toArray();

        $this->assertCount(2, $result);

        $nyc = $result[0]['city'] === 'NYC' ? $result[0] : $result[1];
        $la = $result[0]['city'] === 'LA' ? $result[0] : $result[1];

        $this->assertEquals(2, $nyc['count']);
        $this->assertEquals(1, $la['count']);
    }

    public function test_group_by_mean(): void
    {
        $df = new ArrayDataFrame([
            ['city' => 'NYC', 'age' => 30],
            ['city' => 'LA', 'age' => 20],
            ['city' => 'NYC', 'age' => 40],
        ]);

        $result = $df->groupBy('city')->mean('age')->toArray();

        $nyc = $result[0]['city'] === 'NYC' ? $result[0] : $result[1];
        $this->assertEquals(35.0, $nyc['age']);
    }

    // -----------------------------------------------------------------------
    //  toArray()
    // -----------------------------------------------------------------------

    public function test_toArray_returns_all_data(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertSame($data, $df->toArray());
    }

    public function test_toArray_returns_empty_array_for_empty_dataframe(): void
    {
        $df = new ArrayDataFrame([]);

        $this->assertSame([], $df->toArray());
    }

    // -----------------------------------------------------------------------
    //  Chaining (fluent API)
    // -----------------------------------------------------------------------

    public function test_fluent_api_chaining(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
            ['name' => 'Charlie', 'age' => 35, 'city' => 'NYC'],
            ['name' => 'Diana', 'age' => 28, 'city' => 'LA'],
        ];
        $df = new ArrayDataFrame($data);

        // Filter by city, then select only name and age
        $result = $df
            ->filter('city', '==', 'NYC')
            ->select(['name', 'age']);

        $this->assertSame(2, $result->count());
        $this->assertSame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Charlie', 'age' => 35],
        ], $result->toArray());
    }

    public function test_full_pipeline(): void
    {
        $data = [
            ['city' => 'NYC', 'age' => 30, 'salary' => 50000],
            ['city' => 'LA', 'age' => 25, 'salary' => 60000],
            ['city' => 'NYC', 'age' => 35, 'salary' => 70000],
            ['city' => 'LA', 'age' => 28, 'salary' => 55000],
            ['city' => 'NYC', 'age' => 40, 'salary' => 80000],
        ];
        $df = new ArrayDataFrame($data);

        // People over 30, grouped by city, average salary
        $result = $df
            ->filter('age', '>', 30)
            ->groupBy('city')
            ->mean('salary')
            ->toArray();

        // Only NYC has people over 30 (35 and 40)
        $this->assertCount(1, $result);
        $this->assertEquals('NYC', $result[0]['city']);
        $this->assertEquals(75000.0, $result[0]['salary']);
    }
}
