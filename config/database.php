<?php

declare(strict_types=1);

/**
 * Database Configuration
 *
 * Configure database connections here.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Configure your database connections here.
    | Supported drivers: mysql, pgsql, sqlite, mongodb
    |
    | Each connection automatically uses the appropriate Grammar:
    | - mysql → MySQLGrammar (backticks, positional placeholders)
    | - pgsql → PostgreSQLGrammar (double quotes, numbered placeholders)
    | - sqlite → SQLiteGrammar (double quotes, positional placeholders)
    | - mongodb → MongoDBGrammar (NoSQL query arrays)
    |
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'project_topo'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_NAME', 'project_topo'),
            'username' => env('DB_USER', 'postgres'),
            'password' => env('DB_PASS', ''),
            'charset' => 'utf8',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', 'database/database.sqlite'),
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'host' => env('MONGODB_HOST', 'localhost'),
            'port' => env('MONGODB_PORT', 27017),
            'database' => env('MONGODB_DATABASE', 'project_topo'),
            'username' => env('MONGODB_USERNAME'),
            'password' => env('MONGODB_PASSWORD'),
        ],

        // Example: Analytics database (PostgreSQL)
        'analytics' => [
            'driver' => 'pgsql',
            'host' => env('ANALYTICS_DB_HOST', 'localhost'),
            'port' => env('ANALYTICS_DB_PORT', 5432),
            'database' => env('ANALYTICS_DB_NAME', 'analytics'),
            'username' => env('ANALYTICS_DB_USER', 'postgres'),
            'password' => env('ANALYTICS_DB_PASS', ''),
        ],

        // Example: Logs database (MongoDB)
        'logs' => [
            'driver' => 'mongodb',
            'host' => env('LOGS_DB_HOST', 'localhost'),
            'port' => env('LOGS_DB_PORT', 27017),
            'database' => env('LOGS_DB_NAME', 'logs'),
        ],
    ],
];
