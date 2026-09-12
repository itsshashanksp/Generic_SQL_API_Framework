<?php

require_once __DIR__ . '/../Requests/ApiRequestException.php';

class SqlResourceStatement
{
    private string $prefix;
    private string $body;
    private string $suffix;
    private bool $authoredPagination;

    private function __construct(string $prefix, string $body, string $suffix, bool $authoredPagination)
    {
        $this->prefix = $prefix;
        $this->body = $body;
        $this->suffix = $suffix;
        $this->authoredPagination = $authoredPagination;
    }

    public static function analyze(string $sql): self
    {
        $tokens = self::topLevelTokens($sql);
        if ($tokens === []) {
            throw new RuntimeException('Approved SQL resources must be read-only queries.');
        }

        $first = $tokens[0];
        if ($first['value'] === 'SELECT') {
            $bodyStart = $first['start'];
        } elseif ($first['value'] === 'WITH') {
            $bodyStart = null;
            foreach (array_slice($tokens, 1) as $token) {
                if ($token['value'] === 'SELECT') {
                    $bodyStart = $token['start'];
                    break;
                }
            }
            if ($bodyStart === null) {
                throw new RuntimeException('Approved SQL resources must be read-only queries.');
            }
        } else {
            throw new RuntimeException('Approved SQL resources must be read-only queries.');
        }

        $prefix = substr($sql, 0, $bodyStart);
        $body = substr($sql, $bodyStart);
        $bodyTokens = self::topLevelTokens($body);
        foreach ($bodyTokens as $token) {
            if ($token['value'] === 'INTO') {
                throw new RuntimeException('Approved SQL resources must be read-only queries.');
            }
        }
        if (self::hasTopLevelStatementSeparator($body)) {
            throw new RuntimeException('Approved SQL resources must contain one query statement.');
        }

        $suffix = '';
        foreach ($bodyTokens as $token) {
            if ($token['value'] === 'OPTION'
                && preg_match('/^OPTION\s*\(/i', substr($body, $token['start'])) === 1) {
                $suffix = ' ' . ltrim(substr($body, $token['start']));
                $body = rtrim(substr($body, 0, $token['start']));
                $bodyTokens = self::topLevelTokens($body);
                break;
            }
        }

        $authoredPagination = false;
        foreach ($bodyTokens as $token) {
            if (in_array($token['value'], ['OFFSET', 'FETCH'], true)) {
                $authoredPagination = true;
                break;
            }
        }

        return new self($prefix, $body, $suffix, $authoredPagination);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function suffix(): string
    {
        return $this->suffix;
    }

    public function hasAuthoredPagination(): bool
    {
        return $this->authoredPagination;
    }

    public function injectMappedFilters(?string $whereCondition, ?string $havingCondition): string
    {
        if ($whereCondition === null && $havingCondition === null) {
            return $this->body;
        }

        $tokens = self::topLevelTokens($this->body);
        foreach ($tokens as $token) {
            if (in_array($token['value'], ['UNION', 'INTERSECT', 'EXCEPT'], true)) {
                throw new ApiRequestException(
                    'Runtime filter placement is ambiguous for this SQL resource.',
                    'INVALID_SQL_RUNTIME_FILTER',
                    [['path' => 'filters', 'message' => 'Use an output filter or a dedicated resource for set-operation branches.']]
                );
            }
        }

        $insertions = [];
        if ($whereCondition !== null) {
            $boundary = $this->firstTokenPosition($tokens, ['GROUP', 'HAVING', 'ORDER', 'OFFSET', 'FETCH', 'FOR']);
            $where = $this->firstTokenPosition($tokens, ['WHERE']);
            $position = $boundary ?? strlen($this->body);
            $insertions[] = [
                'position' => $position,
                'priority' => 0,
                'sql' => $where !== null && $where < $position
                    ? " AND ({$whereCondition}) "
                    : " WHERE {$whereCondition} ",
            ];
        }
        if ($havingCondition !== null) {
            $boundary = $this->firstTokenPosition($tokens, ['ORDER', 'OFFSET', 'FETCH', 'FOR']);
            $having = $this->firstTokenPosition($tokens, ['HAVING']);
            $position = $boundary ?? strlen($this->body);
            $insertions[] = [
                'position' => $position,
                'priority' => 1,
                'sql' => $having !== null && $having < $position
                    ? " AND ({$havingCondition}) "
                    : " HAVING {$havingCondition} ",
            ];
        }

        usort($insertions, function (array $left, array $right): int {
            $positionOrder = $right['position'] <=> $left['position'];
            return $positionOrder !== 0
                ? $positionOrder
                : $right['priority'] <=> $left['priority'];
        });
        $body = $this->body;
        foreach ($insertions as $insertion) {
            $body = substr($body, 0, $insertion['position'])
                . $insertion['sql']
                . substr($body, $insertion['position']);
        }
        return $body;
    }

    private function firstTokenPosition(array $tokens, array $values): ?int
    {
        foreach ($tokens as $index => $token) {
            if (in_array($token['value'], $values, true)) {
                if ($token['value'] === 'FOR'
                    && !in_array($tokens[$index + 1]['value'] ?? null, ['JSON', 'XML', 'BROWSE'], true)) {
                    continue;
                }
                return $token['start'];
            }
        }
        return null;
    }

    private static function topLevelTokens(string $sql): array
    {
        $tokens = [];
        self::scan($sql, function (string $type, int $start, string $value) use (&$tokens): void {
            if ($type === 'word') {
                $tokens[] = ['value' => strtoupper($value), 'start' => $start];
            }
        });
        return $tokens;
    }

    private static function hasTopLevelStatementSeparator(string $sql): bool
    {
        $found = false;
        self::scan($sql, function (string $type) use (&$found): void {
            if ($type === 'semicolon') {
                $found = true;
            }
        });
        return $found;
    }

    private static function scan(string $sql, callable $visitor): void
    {
        $length = strlen($sql);
        $depth = 0;
        for ($index = 0; $index < $length;) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($character === "'") {
                for ($index++; $index < $length; $index++) {
                    if ($sql[$index] !== "'") continue;
                    if ($index + 1 < $length && $sql[$index + 1] === "'") {
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                continue;
            }
            if ($character === '"' || $character === '[') {
                $closing = $character === '[' ? ']' : '"';
                for ($index++; $index < $length; $index++) {
                    if ($sql[$index] !== $closing) continue;
                    if ($index + 1 < $length && $sql[$index + 1] === $closing) {
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                continue;
            }
            if ($character === '-' && $next === '-') {
                $newline = strpos($sql, "\n", $index + 2);
                $index = $newline === false ? $length : $newline + 1;
                continue;
            }
            if ($character === '/' && $next === '*') {
                $end = strpos($sql, '*/', $index + 2);
                $index = $end === false ? $length : $end + 2;
                continue;
            }
            if ($character === '(') {
                $depth++;
                $index++;
                continue;
            }
            if ($character === ')') {
                $depth = max(0, $depth - 1);
                $index++;
                continue;
            }
            if ($depth === 0 && $character === ';') {
                $visitor('semicolon', $index, ';');
                $index++;
                continue;
            }
            if ($depth === 0 && preg_match('/[A-Za-z_]/', $character) === 1) {
                $start = $index;
                while ($index < $length && preg_match('/[A-Za-z0-9_]/', $sql[$index]) === 1) {
                    $index++;
                }
                $visitor('word', $start, substr($sql, $start, $index - $start));
                continue;
            }
            $index++;
        }
    }
}
