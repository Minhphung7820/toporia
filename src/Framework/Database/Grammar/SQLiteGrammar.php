<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Grammar;

use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * SQLite Grammar Implementation
 *
 * Compiles QueryBuilder structures into SQLite-specific SQL syntax.
 *
 * SQLite Specifics:
 * - Double quotes for identifiers: "table", "column"
 * - Positional placeholders: ?
 * - LIMIT/OFFSET syntax (similar to MySQL)
 * - ON CONFLICT for upserts (INSERT OR REPLACE)
 * - Limited JSON support (SQLite 3.38+)
 * - No window functions in older versions
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
class SQLiteGrammar extends Grammar
{
    /**
     * SQLite-specific features.
     *
     * @var array<string, bool>
     */
    protected array $features = [
        'window_functions' => true,  // SQLite 3.25+
        'returning_clause' => true,  // SQLite 3.35+
        'upsert' => true,            // INSERT OR REPLACE
        'json_operators' => true,    // SQLite 3.38+ (limited)
        'cte' => true,               // WITH clause
    ];

    /**
     * {@inheritdoc}
     *
     * SQLite uses double quotes to wrap identifiers.
     */
    public function wrapTable(string $table): string
    {
        // Handle qualified tables (database.table)
        if (str_contains($table, '.')) {
            [$database, $tableName] = explode('.', $table, 2);
            return "\"{$database}\".\"{$tableName}\"";
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
     * SQLite uses double quotes to wrap column names.
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

        // Handle JSON operators (SQLite 3.38+)
        if (preg_match('/^(.+?)(->|->>)(.+)$/', $column, $matches)) {
            $baseColumn = "\"{$matches[1]}\"";
            $operator = $matches[2];
            $path = $matches[3];
            return "{$baseColumn}{$operator}{$path}";
        }

        return "\"{$column}\"";
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterPlaceholder(int $index): string
    {
        return '?';
    }

    /**
     * {@inheritdoc}
     *
     * SQLite uses LIMIT (same as MySQL).
     */
    public function compileLimit(int $limit): string
    {
        return "LIMIT {$limit}";
    }

    /**
     * {@inheritdoc}
     *
     * SQLite uses OFFSET (same as MySQL).
     */
    public function compileOffset(int $offset): string
    {
        return "OFFSET {$offset}";
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

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        if (count($values) > 1) {
            $allPlaceholders = implode(', ', array_fill(0, count($values), $placeholders));
            return "INSERT INTO {$table} ({$wrappedColumns}) VALUES {$allPlaceholders}";
        }

        return "INSERT INTO {$table} ({$wrappedColumns}) VALUES {$placeholders}";
    }

    /**
     * {@inheritdoc}
     */
    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->getTable());

        $sets = [];
        foreach (array_keys($values) as $column) {
            $wrappedColumn = $this->wrapColumn($column);
            $sets[] = "{$wrappedColumn} = ?";
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
     * Compile INSERT OR REPLACE (SQLite upsert).
     *
     * @param QueryBuilder $query
     * @param array<string, mixed> $values
     * @return string
     */
    public function compileUpsert(QueryBuilder $query, array $values): string
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

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        return "INSERT OR REPLACE INTO {$table} ({$wrappedColumns}) VALUES {$placeholders}";
    }

    /**
     * Compile VACUUM statement (SQLite optimization).
     *
     * @return string
     */
    public function compileVacuum(): string
    {
        return 'VACUUM';
    }
}
