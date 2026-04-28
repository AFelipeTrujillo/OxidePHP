<?php

declare(strict_types=1);

namespace Tabula\Core;

/**
 * GroupedDataFrame Interface
 *
 * Represents the result of a groupBy operation, supporting aggregation methods.
 *
 * @package Tabula\Core
 */
interface GroupedDataFrame
{
    /**
     * Calculate the mean of a column for each group.
     *
     * @param string $column Name of the numeric column.
     * @return DataFrame A new DataFrame with columns: [group columns, column]
     */
    public function mean(string $column): DataFrame;

    /**
     * Calculate the sum of a column for each group.
     *
     * @param string $column Name of the numeric column.
     * @return DataFrame
     */
    public function sum(string $column): DataFrame;

    /**
     * Get the minimum value of a column for each group.
     *
     * @param string $column
     * @return DataFrame
     */
    public function min(string $column): DataFrame;

    /**
     * Get the maximum value of a column for each group.
     *
     * @param string $column
     * @return DataFrame
     */
    public function max(string $column): DataFrame;

    /**
     * Count the number of rows in each group.
     *
     * @return DataFrame
     */
    public function count(): DataFrame;
}
