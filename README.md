# TabulaPHP 🐘⚡

**TabulaPHP** is a high-performance, zero-dependency DataFrame library for PHP 8.1+. It brings **vectorized-style data processing** and a **fluent, Pandas-inspired API** to the PHP ecosystem — powered by an **in-memory SQLite engine**.

No external extensions required. No compilation needed. Just PHP and SQLite (bundled by default).

---

## Key Features

* **Blazing Fast:** Leverages in-memory SQLite for optimized SQL-based aggregations and filtering.
* **Clean Architecture:** Decoupled design following SOLID principles (Hexagonal Architecture). Easy to swap engines or mock for testing.
* **Fluent API:** An intuitive, modern PHP interface inspired by Pandas and Polars — with method chaining.
* **Full Aggregation Support:** `mean()`, `sum()`, `min()`, `max()`, `count()` — both on DataFrames and grouped data.

---

## Architecture

TabulaPHP is built with a **Ports and Adapters (Hexagonal)** approach to ensure long-term maintainability:

1. **Core (Domain):** Defines the contracts (Interfaces) for `DataFrame` and `GroupedDataFrame`.
2. **Drivers (Adapters):** Implements the interfaces using an in-memory **SQLite** engine.
3. **Infrastructure:** Handles I/O operations like CSV ingestion.

---

## Installation

### Prerequisites

* **PHP 8.1** or higher.
* **SQLite3 extension** (enabled by default in most PHP installations).
* **Composer** for dependency management.

### Setup

```bash
composer require your-handle/oxide-php
```

That's it. No compilation, no configuration, no `php.ini` changes.

---

## Quick Start

```php
use Tabula\Tabula;

// Load a CSV file
$df = Tabula::readCsv('large_dataset.csv');

// Get the average of a numeric column
$averagePrice = $df->mean('price');
echo "The average price is: {$averagePrice}";

// Filter and chain operations
$result = $df
    ->filter('age', '>', 25)
    ->groupBy('city')
    ->mean('salary')
    ->toArray();

print_r($result);
```

---

## Usage Examples

### Basic Operations

```php
use Tabula\Tabula;

$df = Tabula::readCsv('employees.csv');

// Counting rows
echo $df->count(); // 1000

// Aggregations
echo $df->mean('age');     // 34.5
echo $df->sum('salary');   // 5000000
echo $df->min('age');      // 22
echo $df->max('age');      // 65
```

### Column Selection

```php
// Select only specific columns
$subset = $df->select(['name', 'email']);
print_r($subset->toArray());
// [
//   ['name' => 'Alice', 'email' => 'alice@example.com'],
//   ['name' => 'Bob',   'email' => 'bob@example.com'],
//   ...
// ]
```

### Filtering

```php
// Equality filter
$nycEmployees = $df->filter('city', '==', 'NYC');

// Numeric comparison
$seniors = $df->filter('age', '>', 60);
$juniors = $df->filter('age', '<', 30);

// Not equal
$nonManagers = $df->filter('role', '!=', 'Manager');
```

### Group By + Aggregation

```php
// Group by city and get average salary
$avgSalaryByCity = $df
    ->groupBy('city')
    ->mean('salary')
    ->toArray();

// Result: [['city' => 'NYC', 'salary' => 75000], ['city' => 'LA', 'salary' => 62000]]

// Group by department and count employees
$countByDept = $df
    ->groupBy('department')
    ->count()
    ->toArray();

// Group by multiple columns
$result = $df
    ->groupBy(['city', 'department'])
    ->sum('salary')
    ->toArray();
```

### Full Pipeline (Chaining)

```php
// Filter → Group By → Aggregate → Export
$result = Tabula::readCsv('sales.csv')
    ->filter('amount', '>', 1000)
    ->groupBy('region')
    ->mean('amount')
    ->toArray();

// Convert to array and iterate
foreach ($result as $row) {
    echo "{$row['region']}: {$row['amount']}" . PHP_EOL;
}
```

---

## API Reference

### `DataFrame` Interface

| Method | Description | Return Type |
|--------|-------------|-------------|
| `fromCsv(string $path)` | Load data from a CSV file | `DataFrame` |
| `count()` | Number of rows | `int` |
| `mean(string $column)` | Average of a numeric column | `float` |
| `sum(string $column)` | Sum of a numeric column | `float` |
| `min(string $column)` | Minimum value in a column | `mixed` |
| `max(string $column)` | Maximum value in a column | `mixed` |
| `select(array $columns)` | Select specific columns | `DataFrame` |
| `filter(string $column, string $operator, mixed $value)` | Filter rows by condition | `DataFrame` |
| `groupBy(array\|string $columns)` | Group rows for aggregation | `GroupedDataFrame` |
| `toArray()` | Export data as a PHP array | `array` |

### `GroupedDataFrame` Interface

| Method | Description | Return Type |
|--------|-------------|-------------|
| `mean(string $column)` | Average per group | `DataFrame` |
| `sum(string $column)` | Sum per group | `DataFrame` |
| `min(string $column)` | Minimum per group | `DataFrame` |
| `max(string $column)` | Maximum per group | `DataFrame` |
| `count()` | Row count per group | `DataFrame` |

### Supported Filter Operators

| Operator | Description |
|----------|-------------|
| `==` | Equal to |
| `!=` | Not equal to |
| `>` | Greater than |
| `>=` | Greater than or equal |
| `<` | Less than |
| `<=` | Less than or equal |

---

## Development

### Running Tests

We use **PHPUnit** for both unit tests (interface contract) and integration tests (SQLite engine):

```bash
composer test
```

Or manually:

```bash
vendor/bin/phpunit
```

### Test Structure

| Suite | Description |
|-------|-------------|
| **Unit** | Tests the `DataFrame` interface contract using `ArrayDataFrame` (test double) |
| **Integration** | Tests the real `SQLiteDataFrame` implementation with actual CSV files |

### Project Structure

```
src/
├── Core/              # Domain interfaces
│   ├── DataFrame.php
│   └── GroupedDataFrame.php
├── Drivers/           # Concrete implementations
│   └── SQLite/
│       ├── SQLiteDataFrame.php
│       └── SQLiteGroupedDataFrame.php
└── Oxide.php          # Facade / Entry point

tests/
├── Unit/
│   ├── DataFrameTest.php
│   └── ArrayDataFrame.php      # Test double
└── Integration/
    └── SQLiteDataFrameTest.php
```

---

## License

**MIT** — Use it freely in personal and commercial projects.
