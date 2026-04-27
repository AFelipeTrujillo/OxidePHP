<?php

declare(strict_types=1);

namespace Oxide;

use Oxide\Core\DataFrame;
use Oxide\Drivers\Polars\PolarsDataFrame;

/**
 * OxidePHP Entry Point
 *
 * This class provides a static interface to access the high-performance
 * data engines. It acts as a Facade to simplify the creation of DataFrames.
 */
class Oxide
{
    /**
     * Reads a CSV file and returns a Polars-powered DataFrame.
     *
     * @param string $path The path to the CSV file.
     * @return DataFrame
     */
    public static function readCsv(string $path): DataFrame
    {
        // By default, we use the Polars Driver.
        // If we had more drivers, we could decide which one to use here.
        return PolarsDataFrame::fromCsv($path);
    }

    /**
     * Check if the native extension is correctly installed.
     *
     * @return bool
     */
    public static function isReady(): bool
    {
        return extension_loaded('oxide_native');
    }
}