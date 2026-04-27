# OxidePHP 🦀🐘

**OxidePHP** is a high-performance, memory-efficient DataFrame library for PHP 8.1+, powered by the **Polars** engine written in Rust. It brings vectorized data processing and multi-threaded performance to the PHP ecosystem.

## Key Features

* **Blazing Fast:** Leverages Rust's `Polars` for SIMD and multi-threaded data manipulation.
* **Memory Efficient:** Data is stored in Arrow-native memory, bypassing PHP's garbage collector for large datasets.
* **Clean Architecture:** Decoupled design following SOLID principles, making it easy to swap engines or mock for testing.
* **Fluent API:** An intuitive, modern PHP interface inspired by Pandas and Polars.

---

## Architecture

OxidePHP is built with a **Ports and Adapters (Hexagonal)** approach to ensure long-term maintainability:

1.  **Core (Domain):** Defines the contracts (Interfaces) for DataFrames and Series.
2.  **Drivers (Adapters):** Implements the interfaces using the native Rust extension.
3.  **Infrastructure:** Handles I/O operations like CSV, Parquet, and JSON ingestion.



---

## Installation

### Prerequisites

* **PHP 8.1** or higher.
* **Rust & Cargo** (Required to compile the native extension).
* **Composer** for dependency management.

### Setup

1. Install the package via Composer:
   ```bash
   composer require your-handle/oxide-php
   ```

2. Compile the Rust extension:

    ```bash
    cd ext && cargo build --release
    ```

3. Enable the extension in your php.ini:

    ```bash
    extension=oxide_native.so
    ```

## Quick Start

    ```php
    use Oxide\Oxide;

    // Load a massive CSV file in milliseconds
    $df = Oxide::readCsv('large_dataset.csv');

    // Perform high-speed operations
    $averagePrice = $df->mean('price');

    echo "The average price is: {$averagePrice}";

    // Filter and chain (Coming Soon)
    // $df->filter('age', '>', 25)->groupBy('city')->count();

    ```

## Development

### Running Tests

We use **PHPUnit** for testing both the PHP logic and the Rust integration:

    ```bash
    composer test
    ```

### Project Structure

* `ext/`: Rust source code (The Bridge).

* `src/Core/`: Domain interfaces and business logic.

* `src/Drivers/`: Concrete implementations (Polars).

* `src/Infrastructure/`: File system and I/O handlers.