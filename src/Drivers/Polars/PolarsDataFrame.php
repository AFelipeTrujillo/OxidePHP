<?php

declare(strict_types=1);

namespace Oxide\Drivers\Polars;

use Oxide\Core\DataFrame as DataFrameInterface;
use OxideNative\DataFrame as NativeDF;
use InvalidArgumentException;
use RuntimeException;

/**
 * Polars-backed DataFrame implementation.
 *
 * This class acts as a bridge (Adapter) between the Oxide Core and the 
 * high-performance Rust extension.
 */
class PolarsDataFrame implements DataFrameInterface
{
    /**
     * Internal reference to the Rust-side object.
     */
    private NativeDF $native;

    /**
     * @param NativeDF $native The instance provided by the Rust extension.
     */
    public function __construct(NativeDF $native)
    {
        $this->native = $native;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromCsv(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("CSV file not found at: {$path}");
        }

        if (!extension_loaded('oxide_native')) {
            throw new RuntimeException("Oxide native extension is not loaded. Please check your php.ini.");
        }

        // We delegate the heavy lifting to Rust
        return new self(new NativeDF($path));
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->native->get_row_count();
    }

    /**
     * {@inheritdoc}
     */
    public function mean(string $column): float
    {
        // The column_mean method is executed in C/Rust speed
        return $this->native->column_mean($column);
    }

    /**
     * {@inheritdoc}
     */
    public function select(array $columns): self
    {
        $this->native->select_columns($columns);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(string $column, string $operator, mixed $value): self
    {
        // We pass the filter criteria to the Polars engine
        $this->native->apply_filter($column, $operator, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->native->to_php_array();
    }
}