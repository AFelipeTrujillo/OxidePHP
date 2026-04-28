<?php

declare(strict_types=1);

namespace Tabula\Drivers\SQLite;

use Tabula\Core\DataFrame as DataFrameInterface;
use Tabula\Core\GroupedDataFrame;
use InvalidArgumentException;
use SQLite3;
use Tabula\Drivers\SQLite\SQLiteDataFrame;

/**
 * SQLite-backed GroupedDataFrame implementation.
 *
 * Translates group-by aggregation operations into SQL GROUP BY queries.
 *
 * @package Oxide\Drivers\SQLite
 */
class SQLiteGroupedDataFrame implements GroupedDataFrame
{
    /** @var SQLite3 */
    private SQLite3 $db;

    /** @var string */
    private string $tableName;

    /** @var array<string> */
    private array $groupColumns;

    /**
     * @param SQLite3 $db
     * @param string $tableName
     * @param array<string> $groupColumns
     */
    public function __construct(SQLite3 $db, string $tableName, array $groupColumns)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->groupColumns = $groupColumns;
    }

    /**
     * {@inheritdoc}
     */
    public function mean(string $column): DataFrameInterface
    {
        return $this->aggregate('AVG', $column, true);
    }

    /**
     * {@inheritdoc}
     */
    public function sum(string $column): DataFrameInterface
    {
        return $this->aggregate('SUM', $column, true);
    }

    /**
     * {@inheritdoc}
     */
    public function min(string $column): DataFrameInterface
    {
        return $this->aggregate('MIN', $column, false);
    }

    /**
     * {@inheritdoc}
     */
    public function max(string $column): DataFrameInterface
    {
        return $this->aggregate('MAX', $column, false);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): DataFrameInterface
    {
        return $this->aggregate('COUNT', '*', false);
    }

    /**
     * Execute a GROUP BY aggregation query.
     *
     * @param string $fn SQL aggregation function (AVG, SUM, MIN, MAX, COUNT).
     * @param string $column Column name or '*' for COUNT.
     * @param bool $needsCast Whether to cast the column to REAL.
     * @return DataFrameInterface
     */
    private function aggregate(string $fn, string $column, bool $needsCast): DataFrameInterface
    {
        $safeGroupCols = array_map(
            fn ($c) => '"' . SQLiteDataFrame::sanitizeColumnName($c) . '"',
            $this->groupColumns
        );
        $groupByList = implode(', ', $safeGroupCols);

        if ($column === '*') {
            $aggExpr = 'COUNT(*)';
            $resultColumns = array_merge($this->groupColumns, ['count']);
        } else {
            $safeCol = SQLiteDataFrame::sanitizeColumnName($column);
            $colExpr = $needsCast ? "CAST(\"{$safeCol}\" AS REAL)" : "\"{$safeCol}\"";
            $aggExpr = "{$fn}({$colExpr})";
            $resultColumns = array_merge($this->groupColumns, [$column]);
        }

        $newTable = 'df_agg_' . (SQLiteDataFrame::$instanceCount++);
        $selectCols = implode(', ', $safeGroupCols) . ", {$aggExpr} AS \"{$resultColumns[count($this->groupColumns)]}\"";

        $this->db->exec(
            "CREATE TABLE \"{$newTable}\" AS SELECT {$selectCols}
             FROM \"{$this->tableName}\"
             GROUP BY {$groupByList}"
        );

        return new SQLiteDataFrame($this->db, $newTable, $resultColumns);
    }
}
