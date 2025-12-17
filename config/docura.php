<?php

declare(strict_types=1);

/**
 * Docura Configuration
 *
 * High-performance document import/export configuration.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Default Export Driver
    |--------------------------------------------------------------------------
    |
    | The default driver to use for PDF exports. Available: dompdf, tcpdf, mpdf
    |
    */
    'pdf_driver' => env('DOCURA_PDF_DRIVER', 'dompdf'),

    /*
    |--------------------------------------------------------------------------
    | Default Excel Driver
    |--------------------------------------------------------------------------
    |
    | The default driver for Excel exports. Available: phpspreadsheet, native
    |
    */
    'excel_driver' => env('DOCURA_EXCEL_DRIVER', 'phpspreadsheet'),

    /*
    |--------------------------------------------------------------------------
    | Default Word Driver
    |--------------------------------------------------------------------------
    |
    | The default driver for Word exports. Available: phpword, native
    |
    */
    'word_driver' => env('DOCURA_WORD_DRIVER', 'phpword'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Where to store temporary and exported files.
    |
    */
    'storage' => [
        'disk' => env('DOCURA_DISK', 'local'),
        'temp_path' => env('DOCURA_TEMP_PATH', 'storage/docura/temp'),
        'export_path' => env('DOCURA_EXPORT_PATH', 'storage/docura/exports'),
        'import_path' => env('DOCURA_IMPORT_PATH', 'storage/docura/imports'),
        'template_path' => env('DOCURA_TEMPLATE_PATH', 'resources/docura/templates'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    */
    'pdf' => [
        'default_paper_size' => env('DOCURA_PDF_PAPER_SIZE', 'A4'),
        'default_orientation' => env('DOCURA_PDF_ORIENTATION', 'portrait'),
        'default_font' => env('DOCURA_PDF_FONT', 'DejaVu Sans'),
        'default_font_size' => env('DOCURA_PDF_FONT_SIZE', 12),
        'enable_remote_images' => env('DOCURA_PDF_REMOTE_IMAGES', true),
        'margin' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
        'dpi' => env('DOCURA_PDF_DPI', 96),
        'enable_php' => false, // Security: disable PHP execution in templates
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Configuration
    |--------------------------------------------------------------------------
    */
    'excel' => [
        'default_format' => env('DOCURA_EXCEL_FORMAT', 'xlsx'),
        'chunk_size' => env('DOCURA_EXCEL_CHUNK_SIZE', 1000),
        'memory_limit' => env('DOCURA_EXCEL_MEMORY_LIMIT', '512M'),
        'use_streaming' => env('DOCURA_EXCEL_STREAMING', true),
        'csv_delimiter' => env('DOCURA_CSV_DELIMITER', ','),
        'csv_enclosure' => env('DOCURA_CSV_ENCLOSURE', '"'),
        'csv_encoding' => env('DOCURA_CSV_ENCODING', 'UTF-8'),
        'default_styles' => [
            'header' => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['color' => 'F0F0F0'],
                'alignment' => ['horizontal' => 'center'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Word Configuration
    |--------------------------------------------------------------------------
    */
    'word' => [
        'default_format' => env('DOCURA_WORD_FORMAT', 'docx'),
        'default_font' => env('DOCURA_WORD_FONT', 'Arial'),
        'default_font_size' => env('DOCURA_WORD_FONT_SIZE', 11),
        'default_paper_size' => 'A4',
        'default_orientation' => 'portrait',
        'margin' => [
            'top' => 1440,     // in twips (1440 = 1 inch)
            'right' => 1440,
            'bottom' => 1440,
            'left' => 1440,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Configuration
    |--------------------------------------------------------------------------
    */
    'csv' => [
        'delimiter' => env('DOCURA_CSV_DELIMITER', ','),
        'enclosure' => env('DOCURA_CSV_ENCLOSURE', '"'),
        'escape' => env('DOCURA_CSV_ESCAPE', '\\'),
        'encoding' => env('DOCURA_CSV_ENCODING', 'UTF-8'),
        'bom' => env('DOCURA_CSV_BOM', true), // Add BOM for Excel compatibility
        'line_ending' => "\r\n",
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Configuration
    |--------------------------------------------------------------------------
    */
    'html' => [
        'default_charset' => 'UTF-8',
        'minify' => env('DOCURA_HTML_MINIFY', false),
        'include_styles' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'chunk_size' => env('DOCURA_CHUNK_SIZE', 1000),
        'memory_limit' => env('DOCURA_MEMORY_LIMIT', '512M'),
        'time_limit' => env('DOCURA_TIME_LIMIT', 300),
        'use_queue' => env('DOCURA_USE_QUEUE', false),
        'queue_connection' => env('DOCURA_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('DOCURA_QUEUE_NAME', 'docura'),
        'enable_cache' => env('DOCURA_ENABLE_CACHE', true),
        'cache_ttl' => env('DOCURA_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'cache_enabled' => env('DOCURA_TEMPLATE_CACHE', true),
        'cache_path' => 'storage/docura/cache/templates',
        'strict_variables' => false,
        'auto_reload' => env('APP_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'allowed_extensions' => ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'csv', 'html'],
        'max_file_size' => env('DOCURA_MAX_FILE_SIZE', 52428800), // 50MB
        'sanitize_html' => true,
        'allowed_html_tags' => '<p><br><strong><b><em><i><u><ul><ol><li><table><tr><td><th><thead><tbody><h1><h2><h3><h4><h5><h6><img><a><span><div>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('DOCURA_LOGGING', true),
        'channel' => env('DOCURA_LOG_CHANNEL', 'daily'),
        'level' => env('DOCURA_LOG_LEVEL', 'info'),
    ],
];
