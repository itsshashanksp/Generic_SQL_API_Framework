<?php

abstract class WriteSqlBuilder
{
    protected function target(array $resource): string
    {
        return $this->quote($resource['schema']) . '.' . $this->quote($resource['table']);
    }

    protected function quote(string $identifier): string
    {
        return '[' . $identifier . ']';
    }

    protected function outputClause(array $resource, bool $includeOperation = false, bool $includeIdentity = false): string
    {
        $operation = $includeOperation ? '$action' : 'CAST(NULL AS nvarchar(10))';
        $identity = $includeIdentity && $resource['identityColumn'] !== null
            ? 'CONVERT(sql_variant, INSERTED.' . $this->quote($resource['identityColumn']) . ')'
            : 'CAST(NULL AS sql_variant)';
        return 'OUTPUT ' . $operation . ', 1, ' . $identity
            . ' INTO @__WriteOutput ([__operation], [__affected], [__generatedId])';
    }

    protected function batch(string $statement): string
    {
        return 'SET NOCOUNT ON; '
            . 'DECLARE @__WriteOutput TABLE ('
            . '[__operation] nvarchar(10) NULL, '
            . '[__affected] int NOT NULL, '
            . '[__generatedId] sql_variant NULL); '
            . $statement . '; '
            . 'SELECT [__operation], [__affected], [__generatedId] FROM @__WriteOutput;';
    }
}
