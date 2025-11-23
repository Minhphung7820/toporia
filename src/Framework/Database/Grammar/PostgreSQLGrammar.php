<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Grammar;

use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * PostgreSQL Grammar Implementation
 *
 * Compiles QueryBuilder structures into PostgreSQL-specific SQL syntax.
 *
 * PostgreSQL Specifics:
 * - Double quotes for identifiers: "table", "column"
 * - Numbered placeholders: $1, $2, $3...
 * - FETCH FIRST n ROWS ONLY instead of LIMIT
 * - RETURNING clause for INSERT/UPDATE
 * - ON CONFLICT DO UPDATE for upserts
 * - Advanced JSON operators: column->path, column->>path, column#>path
 * - Window functions, CTEs, full-text search
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Database\Grammar
 * @since       2025-01-23
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class PostgreSQLGrammar extends Grammar
{
    /**
     * Parameter index counter for numbered placeholders.
     *
     * @var int
     */
    private int $parameterIndex = 0;

    /**
     * PostgreSQL-specific features.
     *
     * @var array<string, bool>
     */
    protected array $features = [
        'window_functions' => true,
        'returning_clause' => true,  // RETURNING *
        'upsert' => true,            // ON CONFLICT DO UPDATE
        'json_operators' => true,    // Advanced JSON support
        'cte' => true,               // WITH clause
        'full_text_search' => true,  // ts_vector, ts_query
        'arrays' => true,            // Native array support
    ];

    /**
     * {@inheritdoc}
     *
     * PostgreSQL uses double quotes to wrap identifiers.
     */
    public function wrapTable(string $table): string
    {
        // Handle qualified tables (schema.table)
        if (str_contains($table, '.')) {
            [$schema, $tableName] = explode('.', $table, 2);
            return "\"{$schema}\".\"{$tableName}\"";
        }

        // Handle aliases
        if (preg_match('/^(.+?)\s+(?:as\s+)?(.+)$/i', $table, $matches)) {
            return $this->wrapTable($matches[1]) . ' AS "' . $matches[2] . '"';
        }

        return "\"{$table}\"";
    }

    /**
     * {@inheritdoc}
     *
     * PostgreSQL uses double quotes to wrap column names.
     */
    public function wrapColumn(string $column): string
    {
        if ($column === '*' || strtoupper($column) === 'NULL') {
            return $column;
        }

        // Don't wrap subqueries - they are already complete SQL
        // Use base class helper for code reusability
        if ($this->isSubquery($column)) {
            return $this->wrapSubqueryAlias($column, '"');
        }

        // Handle aggregate functions
        if (preg_match('/^(\w+)\s*\((.*)\)(\s+(as)\s+(.+))?$/i', $column, $matches)) {
            $function = strtoupper($matches[1]);
            $argument = trim($matches[2]);
            $hasAlias = !empty($matches[3]);
            $asKeyword = $matches[4] ?? 'AS'; // Preserve original case of AS/as
            $alias = $matches[5] ?? null;

            if ($argument !== '*' && !is_numeric($argument)) {
                $argument = $this->wrapColumn($argument);
            }

            $result = "{$function}({$argument})";
            return ($hasAlias && $alias) ? $result . " {$asKeyword} \"{$alias}\"" : $result;
        }

        // Handle qualified columns
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return $this->wrapTable($table) . '.' . ($col === '*' ? '*' : "\"{$col}\"");
        }

        // Handle aliases (column AS alias or column as alias)
        if (preg_match('/^(.+?)\s+(as)\s+(.+)$/i', $column, $matches)) {
            $column = $matches[1];
            $asKeyword = $matches[2]; // Preserve original case of AS/as
            $alias = $matches[3];
            return $this->wrapColumn($column) . " {$asKeyword} \"{$alias}\"";
        }

        // Handle JSON operators (keep as-is)
        if (preg_match('/^(.+?)(->|->>|#>|#>>)(.+)$/', $column, $matches)) {
            $baseColumn = "\"{$matches[1]}\"";
            $operator = $matches[2];
            $path = $matches[3];
            return "{$baseColumn}{$operator}{$path}";
        }

        return "\"{$column}\"";
    }

    /**
     * {@inheritdoc}
     *
     * PostgreSQL uses numbered placeholders: $1, $2, $3...
     */
    public function getParameterPlaceholder(int $index): string
    {
        return '$' . ($index + 1);
    }

    /**
     * {@inheritdoc}
     *
     * PostgreSQL uses FETCH FIRST n ROWS ONLY.
     */
    public function compileLimit(int $limit): string
    {
        return "FETCH FIRST {$limit} ROWS ONLY";
    }

    /**
     * {@inheritdoc}
     *
     * PostgreSQL uses OFFSET n ROWS.
     */
    public function compileOffset(int $offset): string
    {
        return "OFFSET {$offset} ROWS";
    }

    /**
     * {@inheritdoc}
     */
    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->getTable());

        if (isset($values[0]) && is_array($values[0])) {
            $columns = array_keys($values[0]);
        } else {
            $columns = array_keys($values);
            $values = [$values];
        }

        $wrappedColumns = implode(', ', array_map(
            fn($col) => $this->wrapColumn($col),
            $columns
        ));

        $index = 1;
        $placeholders = '(' . implode(', ', array_map(
            fn() => '$' . $index++,
            $columns
        )) . ')';

        if (count($values) > 1) {
            $allPlaceholders = [];
            foreach ($values as $row) {
                $rowPlaceholders = [];
                foreach ($row as $value) {
                    $rowPlaceholders[] = '$' . $index++;
                }
                $allPlaceholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }
            return "INSERT INTO {$table} ({$wrappedColumns}) VALUES " . implode(', ', $allPlaceholders);
        }

        return "INSERT INTO {$table} ({$wrappedColumns}) VALUES {$placeholders}";
    }

    /**
     * Compile INSERT with RETURNING clause.
     *
     * @param QueryBuilder $query
     * @param array<string, mixed> $values
     * @param array<string> $returning Columns to return
     * @return string
     */
    public function compileInsertReturning(QueryBuilder $query, array $values, array $returning = ['*']): string
    {
        $insertSql = $this->compileInsert($query, $values);
        $returningColumns = implode(', ', array_map(
            fn($col) => $this->wrapColumn($col),
            $returning
        ));

        return "{$insertSql} RETURNING {$returningColumns}";
    }

    /**
     * {@inheritdoc}
     */
    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->getTable());

        $index = 1;
        $sets = [];
        foreach (array_keys($values) as $column) {
            $wrappedColumn = $this->wrapColumn($column);
            $sets[] = "{$wrappedColumn} = \$" . $index++;
        }

        $setClause = implode(', ', $sets);
        $whereClause = $this->compileWheres($query);

        return "UPDATE {$table} SET {$setClause} {$whereClause}";
    }

    /**
     * {@inheritdoc}
     */
    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->wrapTable($query->getTable());
        $whereClause = $this->compileWheres($query);

        return "DELETE FROM {$table} {$whereClause}";
    }

    /**
     * Compile INSERT with ON CONFLICT (PostgreSQL upsert).
     *
     * @param QueryBuilder $query
     * @param array<string, mixed> $values
     * @param array<string> $conflictColumns Conflict target columns
     * @param array<string> $updateColumns Columns to update on conflict
     * @return string
     */
    public function compileUpsert(
        QueryBuilder $query,
        array $values,
        array $conflictColumns,
        array $updateColumns
    ): string {
        $insertSql = $this->compileInsert($query, $values);

        $conflict = implode(', ', array_map(
            fn($col) => $this->wrapColumn($col),
            $conflictColumns
        ));

        $updates = [];
        foreach ($updateColumns as $column) {
            $wrappedColumn = $this->wrapColumn($column);
            $updates[] = "{$wrappedColumn} = EXCLUDED.{$wrappedColumn}";
        }

        $onConflict = implode(', ', $updates);

        return "{$insertSql} ON CONFLICT ({$conflict}) DO UPDATE SET {$onConflict}";
    }
}
