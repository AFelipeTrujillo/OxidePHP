<?php

declare(strict_types=1);

namespace Tabula;

use Tabula\Core\DataFrame;
use Tabula\Drivers\SQLite\SQLiteDataFrame;

/**
 * TabulaPHP Entry Point
 *
 * This class provides a static interface to access the data engine.
 * It acts as a Facade to simplify the creation of DataFrames.
 *
 * By default, TabulaPHP uses the SQLite engine, which requires no external
 * dependencies and provides excellent performance for medium-sized datasets
 * through optimized in-memory SQL queries.
 */
class Tabula
{
    /**
     * Reads a CSV file and returns a SQLite-powered DataFrame.
     *
     * @param string $path The path to the CSV file.
     * @return DataFrame
     */
    public static function readCsv(string $path): DataFrame
    {
        return SQLiteDataFrame::fromCsv($path);
    }

    /**
     * Check if the required SQLite extension is available.
     *
     * @return bool
     */
    public static function isReady(): bool
    {
        return extension_loaded('sqlite3');
    }
}