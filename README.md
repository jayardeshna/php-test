# Historical Data Processor for Meevo Sales Data

This Python-based system processes all historical sales data from 2021 to today, converting raw pipe-delimited data to clean CSV format for further processing.

## Overview

The system replaces the PHP-based processing with a more robust Python solution that can handle large volumes of historical data efficiently. It processes data from all location folders and converts the pipe-delimited format (`|@|` field separator, `$|` record separator) to standard CSV files.

## Features

- **Parallel Processing**: Process multiple locations simultaneously
- **Date Range Filtering**: Process specific date ranges (2021-today)
- **Data Validation**: Built-in quality checks and validation
- **Progress Tracking**: Real-time progress monitoring and logging
- **Error Handling**: Robust error handling with detailed logging
- **Memory Efficient**: Processes large datasets without memory issues
- **Configurable**: JSON-based configuration for easy customization

## File Structure

```
├── historical_data_processor.py    # Main processing script
├── run_historical_processing.py    # Batch runner with user interface
├── data_validator.py              # Data validation and quality checks
├── config.json                    # Configuration file
├── requirements.txt               # Python dependencies
├── README.md                      # This file
├── row-data/                      # Raw data directory (your existing data)
│   ├── Location201555/
│   ├── Location201556/
│   └── ...
└── cleaned-csv-historical/        # Output directory (will be created)
    ├── Location201555/
    ├── Location201556/
    └── ...
```

## Installation

1. **Install Python Dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

2. **Verify Installation**:
   ```bash
   python run_historical_processing.py --check-deps
   ```

## Quick Start

### 1. Estimate Processing Time
```bash
python run_historical_processing.py --estimate-only
```

### 2. Process All Historical Data (2021-Today)
```bash
python run_historical_processing.py
```

### 3. Process Specific Date Range
```bash
python run_historical_processing.py --start-date 20210101 --end-date 20231231
```

### 4. Process Single Location
```bash
python run_historical_processing.py --location Location201555
```

### 5. Validate Processed Data
```bash
python data_validator.py
```

## Detailed Usage

### Main Processing Script

```bash
python historical_data_processor.py [OPTIONS]
```

**Options:**
- `--raw-data-dir`: Raw data directory (default: row-data)
- `--output-dir`: Output directory (default: cleaned-csv-historical)
- `--start-date`: Start date in YYYYMMDD format (default: 20210101)
- `--end-date`: End date in YYYYMMDD format (default: today)
- `--max-workers`: Number of parallel workers (default: 4)
- `--location`: Process specific location only

### Batch Runner

```bash
python run_historical_processing.py [OPTIONS]
```

**Additional Options:**
- `--config`: Configuration file path (default: config.json)
- `--estimate-only`: Only estimate processing time
- `--check-deps`: Check dependencies only

### Data Validator

```bash
python data_validator.py [OPTIONS]
```

**Options:**
- `--data-dir`: Processed data directory (default: cleaned-csv-historical)
- `--output-report`: Save report to file
- `--output-json`: Save JSON results to file
- `--location`: Validate specific location only

## Configuration

Edit `config.json` to customize processing:

```json
{
  "processing_config": {
    "raw_data_directory": "row-data",
    "output_directory": "cleaned-csv-historical",
    "start_date": "20210101",
    "max_parallel_workers": 4
  },
  "data_quality": {
    "skip_empty_records": true,
    "validate_dates": true,
    "clean_special_characters": true
  }
}
```

## Data Processing Flow

1. **Discovery**: Scan all location folders in raw data directory
2. **File Analysis**: Identify file types and extract dates from filenames
3. **Data Parsing**: Convert pipe-delimited data to structured format
4. **Cleaning**: Remove control characters and handle encoding issues
5. **CSV Generation**: Create clean CSV files with proper headers
6. **Validation**: Verify data quality and completeness

## File Type Mappings

The system processes ONLY these specific file types (all others are ignored):

| Raw File Prefix | Output File Type | Description |
|----------------|------------------|-------------|
| Sale_ | sale | Main sales transactions |
| SaleLine_ | saleline | Individual sale line items |
| SaleLineProduct_ | salelineproduct | Product-specific sale lines |
| SaleLineService_ | salelineservice | Service-specific sale lines |
| SaleLineEmployee_ | sale_line_employee | Employee assignments to sale lines |
| SaleLinePayment_ | sale_line_payment | Payment information for sale lines |
| SaleLineTax_ | sale_line_tax | Tax information for sale lines |
| SaleEmployeeTip_ | sale_employee_tip | Employee tip data |
| ClientGiftCard_ | clientgiftcard | Gift card transactions |
| ClientMembership_T_ | clientmembership | Membership data |
| ClientService_T_ | clientservice | Client service records |

**Note**: All other file types (Client_, Employee_, Appointment_, etc.) are automatically excluded from processing.

## Output Structure

Processed files are organized by location and date:

```
cleaned-csv-historical/
├── Location201555/
│   ├── sale_20210609.csv
│   ├── saleline_20210609.csv
│   ├── salelineproduct_20210609.csv
│   └── ...
├── Location201556/
│   ├── sale_20210609.csv
│   └── ...
```

## Performance Optimization

- **Parallel Processing**: Uses multiple CPU cores
- **Memory Management**: Processes files in chunks
- **Efficient Parsing**: Optimized regex and string operations
- **Progress Tracking**: Real-time monitoring

## Error Handling

- **Encoding Issues**: Automatic encoding detection and conversion
- **Malformed Data**: Graceful handling of corrupted records
- **Missing Files**: Continues processing other files
- **Memory Limits**: Automatic memory management

## Logging

All processing activities are logged to:
- Console output (real-time)
- `historical_data_processor.log` (detailed log file)

Log levels: INFO, WARNING, ERROR

## Validation and Quality Checks

The validator checks for:
- File structure integrity
- Data completeness
- Duplicate records
- Missing required columns
- Encoding issues
- Empty files

## Troubleshooting

### Common Issues

1. **Memory Errors**: Reduce `max_workers` in config
2. **Encoding Errors**: Files are automatically handled with fallback encoding
3. **Missing Dependencies**: Run `pip install -r requirements.txt`
4. **Permission Errors**: Ensure write access to output directory

### Performance Tips

- Use SSD storage for better I/O performance
- Increase `max_workers` on multi-core systems
- Process locations individually for very large datasets
- Monitor memory usage during processing

## Integration with Existing PHP System

After processing, the cleaned CSV files can be used with your existing PHP scripts:

1. Update PHP scripts to read from `cleaned-csv-historical/` directory
2. Remove date filtering (data is already filtered)
3. Use the same file type mappings for consistency

## Next Steps

After running the historical data processor:

1. **Validate Results**: Run the data validator
2. **Review Reports**: Check processing and validation reports
3. **Update PHP Scripts**: Modify existing scripts to use cleaned data
4. **Database Import**: Use cleaned CSV files for database operations
5. **Schedule Regular Processing**: Set up automated processing for new data

## Support

For issues or questions:
1. Check the log files for detailed error messages
2. Run validation to identify data quality issues
3. Use `--estimate-only` to preview processing requirements
4. Process single locations to isolate problems
