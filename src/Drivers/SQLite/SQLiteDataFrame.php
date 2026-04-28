<?php

declare(strict_types=1);

namespace Tabula\Drivers\SQLite;

use Tabula\Core\DataFrame as DataFrameInterface;
use Tabula\Core\GroupedDataFrame;
use InvalidArgumentException;
use SQLite3;

/**
 * SQLite-backed DataFrame implementation.
 *
 * This class stores data in an in-memory SQLite database, translating
 * DataFrame operations into SQL queries. This provides:
 * - No external dependencies (SQLite comes bundled with PHP)
 * - Excellent performance for medium-sized datasets
 * - Full SQL power (GROUP BY, aggregations, filtering)
 *
 * @package Oxide\Drivers\SQLite
 */
class SQLiteDataFrame implements DataFrameInterface
{
    /** @var SQLite3 In-memory database connection. */
    private SQLite3 $db;

    /** @var string The name of the temporary table holding this DataFrame's data. */
    private string $tableName;

    /** @var array<string> List of column names. */
    private array $columns;

    /** @var int Number of instances (for unique table naming). */
    public static int $instanceCount = 0;

    /**
     * @param SQLite3 $db Database connection.
     * @param string $tableName Temporary table name.
     * @param array<string> $columns Column names.
     */
    public function __construct(SQLite3 $db, string $tableName, array $columns)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->columns = $columns;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromCsv(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("CSV file not found at: {$path}");
        }

        $db = new SQLite3(':memory:');
        $tableName = 'df_' . (++self::$instanceCount);

        // Open the CSV file
        if (($handle = fopen($path, 'r')) === false) {
            throw new InvalidArgumentException("Cannot open CSV file: {$path}");
        }

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            throw new InvalidArgumentException("Empty or invalid CSV file: {$path}");
        }

        $headers = array_map('trim', $headers);

        // Create table with TEXT columns (SQLite handles type casting automatically)
        $columnDefs = [];
        foreach ($headers as $header) {
            $safeName = self::sanitizeColumnName($header);
            $columnDefs[] = "\"{$safeName}\" TEXT";
        }

        $createSql = sprintf(
            'CREATE TABLE "%s" (%s)',
            $tableName,
            implode(', ', $columnDefs)
        );
        $db->exec($createSql);

        // Prepare INSERT statement for batch insert
        $placeholders = implode(', ', array_fill(0, count($headers), '?'));
        $insertSql = sprintf(
            'INSERT INTO "%s" VALUES (%s)',
            $tableName,
            $placeholders
        );
        $stmt = $db->prepare($insertSql);

        // Read and insert data row by row
        $rowCount = 0;
        while (($data = fgetcsv($handle)) !== false) {
            // Pad or trim row to match header count
            $dataCount = count($data);
            $headerCount = count($headers);

            if ($dataCount < $headerCount) {
                $data = array_pad($data, $headerCount, null);
            } elseif ($dataCount > $headerCount) {
                $data = array_slice($data, 0, $headerCount);
            }

            foreach ($data as $i => $value) {
                $stmt->bindValue($i + 1, $value !== '' ? $value : null);
            }

            $stmt->execute();
            $rowCount++;
        }

        fclose($handle);

        if ($rowCount === 0) {
            throw new InvalidArgumentException("CSV file has no data rows: {$path}");
        }

        return new self($db, $tableName, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $result = $this->db->querySingle("SELECT COUNT(*) FROM \"{$this->tableName}\"");
        return (int) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mean(string $column): float
    {
        $this->validateColumnExists($column);

        $safeCol = self::sanitizeColumnName($column);
        $result = $this->db->querySingle(
            "SELECT AVG(CAST(\"{$safeCol}\" AS REAL)) FROM \"{$this->tableName}\""
        );

        if ($result === null) {
            return 0.0;
        }

        return (float) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function sum(string $column): float
    {
        $this->validateColumnExists($column);

        $safeCol = self::sanitizeColumnName($column);
        $result = $this->db->querySingle(
            "SELECT SUM(CAST(\"{$safeCol}\" AS REAL)) FROM \"{$this->tableName}\""
        );

        if ($result === null) {
            return 0.0;
        }

        return (float) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function min(string $column): mixed
    {
        $this->validateColumnExists($column);

        $safeCol = self::sanitizeColumnName($column);
        return $this->db->querySingle(
            "SELECT MIN(\"{$safeCol}\") FROM \"{$this->tableName}\""
        );
    }

    /**
     * {@inheritdoc}
     */
    public function max(string $column): mixed
    {
        $this->validateColumnExists($column);

        $safeCol = self::sanitizeColumnName($column);
        return $this->db->querySingle(
            "SELECT MAX(\"{$safeCol}\") FROM \"{$this->tableName}\""
        );
    }

    /**
     * {@inheritdoc}
     */
    public function select(array $columns): self
    {
        foreach ($columns as $col) {
            $this->validateColumnExists($col);
        }

        $safeCols = array_map(fn ($c) => '"' . self::sanitizeColumnName($c) . '"', $columns);
        $selectList = implode(', ', $safeCols);

        $newTable = 'df_' . (++self::$instanceCount);
        $this->db->exec(
            "CREATE TABLE \"{$newTable}\" AS SELECT {$selectList} FROM \"{$this->tableName}\""
        );

        return new self($this->db, $newTable, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(string $column, string $operator, mixed $value): self
    {
        $this->validateColumnExists($column);

        $safeCol = self::sanitizeColumnName($column);
        $sqlOperator = match ($operator) {
            '==' => '=',
            '!=' => '!=',
            '>'  => '>',
            '>=' => '>=',
            '<'  => '<',
            '<=' => '<=',
            default => throw new InvalidArgumentException(
                "Unsupported operator: {$operator}. Use: ==, !=, >, >=, <, <="
            ),
        };

        // Escape value to prevent SQL injection
        $escapedValue = $this->db->escapeString((string) $value);
        $whereClause = "CAST(\"{$safeCol}\" AS TEXT) {$sqlOperator} '{$escapedValue}'";

        // Also try numeric comparison if value is numeric
        if (is_numeric($value)) {
            $whereClause = "CAST(\"{$safeCol}\" AS REAL) {$sqlOperator} {$value}";
        }

        $newTable = 'df_' . (++self::$instanceCount);
        $this->db->exec(
            "CREATE TABLE \"{$newTable}\" AS SELECT * FROM \"{$this->tableName}\" WHERE {$whereClause}"
        );

        return new self($this->db, $newTable, $this->columns);
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(array|string $columns): GroupedDataFrame
    {
        $groupColumns = is_string($columns) ? [$columns] : $columns;

        foreach ($groupColumns as $col) {
            $this->validateColumnExists($col);
        }

        return new SQLiteGroupedDataFrame($this->db, $this->tableName, $groupColumns);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $result = $this->db->query("SELECT * FROM \"{$this->tableName}\"");
        $rows = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Validate that a column exists in the DataFrame.
     *
     * @param string $column
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateColumnExists(string $column): void
    {
        if (!in_array($column, $this->columns, true)) {
            throw new InvalidArgumentException(
                "Column '{$column}' does not exist. Available columns: " . implode(', ', $this->columns)
            );
        }
    }

    /**
     * Sanitize a column name for safe use in SQL.
     *
     * @param string $name
     * @return string
     */
    public static function sanitizeColumnName(string $name): string
    {
        // Replace non-alphanumeric characters (except underscore) with underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
}
