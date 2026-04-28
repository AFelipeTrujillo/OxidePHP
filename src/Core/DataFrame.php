<?php

declare(strict_types=1);

namespace Oxide\Core;

/**
 * DataFrame Interface
 *
 * This contract defines the standard operations for tabular data manipulation
 * within the OxidePHP ecosystem. By coding to this interface, we ensure
 * the application remains decoupled from the underlying engine.
 *
 * @package Oxide\Core
 */
interface DataFrame
{
    /**
     * Create a new DataFrame instance from a CSV file.
     *
     * @param string $path Path to the .csv file.
     * @return self
     */
    public static function fromCsv(string $path): self;

    /**
     * Get the total number of rows in the DataFrame.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Calculate the arithmetic mean of a specific column.
     *
     * @param string $column Name of the numeric column.
     * @return float
     * @throws \InvalidArgumentException If the column does not exist or is not numeric.
     */
    public function mean(string $column): float;

    /**
     * Calculate the sum of a specific column.
     *
     * @param string $column Name of the numeric column.
     * @return float
     * @throws \InvalidArgumentException If the column does not exist or is not numeric.
     */
    public function sum(string $column): float;

    /**
     * Get the minimum value of a specific column.
     *
     * @param string $column Name of the column.
     * @return mixed
     * @throws \InvalidArgumentException If the column does not exist.
     */
    public function min(string $column): mixed;

    /**
     * Get the maximum value of a specific column.
     *
     * @param string $column Name of the column.
     * @return mixed
     * @throws \InvalidArgumentException If the column does not exist.
     */
    public function max(string $column): mixed;

    /**
     * Select specific columns from the DataFrame.
     *
     * @param array<string> $columns List of column names.
     * @return self
     */
    public function select(array $columns): self;

    /**
     * Filter rows based on a simple comparison.
     * * Example: $df->filter('age', '>', 18);
     *
     * @param string $column
     * @param string $operator (e.g., '>', '<', '==', '!=')
     * @param mixed $value
     * @return self
     */
    public function filter(string $column, string $operator, mixed $value): self;

    /**
     * Group by one or more columns and apply an aggregation.
     *
     * Example: $df->groupBy('city')->mean('age');
     *
     * @param array<string>|string $columns Column(s) to group by.
     * @return GroupedDataFrame A grouped object that supports aggregation methods.
     */
    public function groupBy(array|string $columns): GroupedDataFrame;

    /**
     * Return the data as a native PHP array.
     * Use this sparingly as it brings data into PHP memory.
     *
     * @return array<mixed>
     */
    public function toArray(): array;
}