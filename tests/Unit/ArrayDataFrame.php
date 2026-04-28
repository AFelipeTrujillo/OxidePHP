<?php

declare(strict_types=1);

namespace Tabula\Tests\Unit;

use Tabula\Core\DataFrame as DataFrameInterface;
use Tabula\Core\GroupedDataFrame;

/**
 * ArrayDataFrame — Test Double for DataFrame Interface
 *
 * This lightweight implementation allows us to test the interface contract
 * without requiring any external engine. It stores data as a simple
 * array of associative arrays.
 *
 * @internal — Only for testing purposes.
 */
class ArrayDataFrame implements DataFrameInterface, \Countable
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @var array<string> */
    private array $columns;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string>|null $columns Optional list of column names.
     */
    public function __construct(array $rows, ?array $columns = null)
    {
        $this->rows = $rows;
        $this->columns = $columns ?? (empty($rows) ? [] : array_keys($rows[0]));
    }

    // -----------------------------------------------------------------------
    //  DataFrame Interface Implementation
    // -----------------------------------------------------------------------

    public static function fromCsv(string $path): DataFrameInterface
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("CSV file not found at: {$path}");
        }

        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);

            if ($headers !== false) {
                $headers = array_map('trim', $headers);

                while (($data = fgetcsv($handle)) !== false) {
                    $row = [];
                    foreach ($headers as $i => $header) {
                        $row[$header] = $data[$i] ?? null;
                    }
                    $rows[] = $row;
                }
            }

            fclose($handle);
        }

        return new self($rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function mean(string $column): float
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' does not exist."
            );
        }

        $values = array_filter(
            array_column($this->rows, $column),
            fn ($v) => is_numeric($v)
        );

        $numericCount = count($values);

        if ($numericCount === 0) {
            $allValues = array_column($this->rows, $column);
            if (!empty($allValues)) {
                throw new \InvalidArgumentException(
                    "Column '{$column}' is non-numeric."
                );
            }

            return 0.0;
        }

        $totalValues = array_column($this->rows, $column);
        if (count($values) !== count($totalValues)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' is non-numeric."
            );
        }

        return array_sum($values) / count($values);
    }

    public function sum(string $column): float
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' does not exist."
            );
        }

        $values = array_column($this->rows, $column);
        $numericValues = array_filter($values, fn ($v) => is_numeric($v));

        if (count($numericValues) !== count($values)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' is non-numeric."
            );
        }

        return array_sum($numericValues);
    }

    public function min(string $column): mixed
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' does not exist."
            );
        }

        $values = array_column($this->rows, $column);
        return empty($values) ? null : min($values);
    }

    public function max(string $column): mixed
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' does not exist."
            );
        }

        $values = array_column($this->rows, $column);
        return empty($values) ? null : max($values);
    }

    public function select(array $columns): DataFrameInterface
    {
        $selected = [];

        foreach ($this->rows as $row) {
            $newRow = [];
            foreach ($columns as $col) {
                $newRow[$col] = $row[$col] ?? null;
            }
            $selected[] = $newRow;
        }

        return new self($selected, $columns);
    }

    public function filter(string $column, string $operator, mixed $value): DataFrameInterface
    {
        $filtered = array_filter(
            $this->rows,
            function (array $row) use ($column, $operator, $value): bool {
                $cell = $row[$column] ?? null;

                return match ($operator) {
                    '==' => $cell == $value,
                    '!=' => $cell != $value,
                    '>'  => $cell > $value,
                    '>=' => $cell >= $value,
                    '<'  => $cell < $value,
                    '<=' => $cell <= $value,
                    default => throw new \InvalidArgumentException(
                        "Unsupported operator: {$operator}"
                    ),
                };
            }
        );

        return new self(array_values($filtered), $this->columns);
    }

    public function groupBy(array|string $columns): GroupedDataFrame
    {
        $groupColumns = is_string($columns) ? [$columns] : $columns;

        return new ArrayGroupedDataFrame($this->rows, $groupColumns);
    }

    public function toArray(): array
    {
        return $this->rows;
    }
}

/**
 * @internal — Only for testing purposes.
 */
class ArrayGroupedDataFrame implements GroupedDataFrame
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @var array<string> */
    private array $groupColumns;

    public function __construct(array $rows, array $groupColumns)
    {
        $this->rows = $rows;
        $this->groupColumns = $groupColumns;
    }

    public function mean(string $column): DataFrameInterface
    {
        return $this->aggregate('AVG', $column);
    }

    public function sum(string $column): DataFrameInterface
    {
        return $this->aggregate('SUM', $column);
    }

    public function min(string $column): DataFrameInterface
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): DataFrameInterface
    {
        return $this->aggregate('MAX', $column);
    }

    public function count(): DataFrameInterface
    {
        $groups = $this->getGroups();
        $result = [];

        foreach ($groups as $key => $group) {
            $row = [];
            foreach ($this->groupColumns as $i => $col) {
                $row[$col] = $group['keys'][$i];
            }
            $row['count'] = count($group['rows']);
            $result[] = $row;
        }

        return new ArrayDataFrame($result);
    }

    private function aggregate(string $fn, string $column): DataFrameInterface
    {
        $groups = $this->getGroups();
        $result = [];
        $isNumeric = in_array($fn, ['AVG', 'SUM']);

        foreach ($groups as $key => $group) {
            $values = array_column($group['rows'], $column);
            $numericValues = array_filter($values, fn ($v) => is_numeric($v));

            if ($isNumeric && count($numericValues) !== count($values)) {
                throw new \InvalidArgumentException(
                    "Column '{$column}' is non-numeric."
                );
            }

            $row = [];
            foreach ($this->groupColumns as $i => $col) {
                $row[$col] = $group['keys'][$i];
            }

            $row[$column] = match ($fn) {
                'AVG' => count($numericValues) > 0 ? array_sum($numericValues) / count($numericValues) : 0.0,
                'SUM' => array_sum($numericValues),
                'MIN' => !empty($values) ? min($values) : null,
                'MAX' => !empty($values) ? max($values) : null,
            };

            $result[] = $row;
        }

        return new ArrayDataFrame($result);
    }

    private function getGroups(): array
    {
        $groups = [];

        foreach ($this->rows as $row) {
            $keyParts = [];
            foreach ($this->groupColumns as $col) {
                $keyParts[] = $row[$col] ?? '__NULL__';
            }
            $key = implode('|', $keyParts);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'keys' => $keyParts,
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        return $groups;
    }
}
