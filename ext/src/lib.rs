use ext_php_rs::prelude::*;
use polars::prelude::*;
use std::path::Path;

#[php_class(name = "OxideNative\\DataFrame")]
pub struct NativeDataFrame {
    // Almacenamos el DataFrame de Polars en la estructura
    pub df: DataFrame,
}

#[php_impl]
impl NativeDataFrame {
    /// Constructor: Se llama desde PHP con new NativeDF($path)
    pub fn __construct(path: String) -> PhpResult<Self> {
        let p = Path::new(&path);
        
        // Verificamos que el archivo existe desde Rust por seguridad
        if !p.exists() {
            return Err(PhpException::default(format!("File not found: {}", path)));
        }

        // En polars 0.44+, CsvReader usa CsvReadOptions
        let df = CsvReadOptions::from_path(p)
            .map_err(|e| PhpException::default(format!("Polars Error: {}", e)))?
            .into_reader_with_parse_options(None)
            .finish()
            .map_err(|e| PhpException::default(format!("Failed to parse CSV: {}", e)))?;

        Ok(NativeDataFrame { df })
    }

    /// Retorna el número de filas (height) del DataFrame
    pub fn get_row_count(&self) -> usize {
        self.df.height()
    }

    /// Calcula el promedio de una columna
    pub fn column_mean(&self, col_name: String) -> PhpResult<f64> {
        // Buscamos la columna
        let column = self.df.column(&col_name)
            .map_err(|e| PhpException::default(format!("Column error: {}", e)))?;

        // En polars 0.44+, convertimos Column a Series y calculamos mean
        let series = column.as_series().ok_or_else(|| {
            PhpException::default("Cannot convert column to series".into())
        })?;

        let mean_val = series.mean().ok_or_else(|| {
            PhpException::default("Cannot calculate mean for non-numeric column".into())
        })?;

        Ok(mean_val)
    }
}

/// Función obligatoria para registrar el módulo en PHP
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}