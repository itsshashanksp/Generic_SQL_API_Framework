<?php

require_once __DIR__ . '/../MetadataRepository.php';

/** Adds request-local CTE output metadata without weakening physical-table validation. */
class ScopedMetadataRepository extends MetadataRepository
{
    private MetadataRepository $delegate;
    private array $virtualTables = [];

    public function __construct(MetadataRepository $delegate)
    {
        $this->delegate = $delegate;
    }

    public function setVirtualTables(array $tables): void
    {
        $this->virtualTables = $tables;
    }

    public function getVirtualTables(): array
    {
        return $this->virtualTables;
    }

    public function tableExists($table)
    {
        return $this->findVirtualTable((string)$table) !== null
            || $this->delegate->tableExists($table);
    }

    public function columnExists($table, $column)
    {
        $virtualTable = $this->findVirtualTable((string)$table);
        if ($virtualTable === null) {
            return $this->delegate->columnExists($table, $column);
        }
        foreach ($this->virtualTables[$virtualTable] as $virtualColumn) {
            if (strcasecmp($virtualColumn, (string)$column) === 0) {
                return true;
            }
        }
        return false;
    }

    public function getColumnDataType($table, $column)
    {
        return $this->findVirtualTable((string)$table) !== null
            ? null
            : $this->delegate->getColumnDataType($table, $column);
    }

    public function getColumns($table)
    {
        $virtualTable = $this->findVirtualTable((string)$table);
        if ($virtualTable === null) {
            return $this->delegate->getColumns($table);
        }
        return [
            'data' => array_map(
                fn (string $column): array => ['COLUMN_NAME' => $column],
                $this->virtualTables[$virtualTable]
            ),
        ];
    }

    private function findVirtualTable(string $table): ?string
    {
        foreach (array_keys($this->virtualTables) as $candidate) {
            if (strcasecmp($candidate, $table) === 0) {
                return $candidate;
            }
        }
        return null;
    }
}
