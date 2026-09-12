<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../Requests/ApiRequestException.php';

class SqlResourceDiscovery
{
    private string $root;
    private array $excludedDirectories;
    private ?array $resources = null;

    public function __construct(?array $settings = null, ?string $root = null)
    {
        if ($settings === null) {
            $configFile = ROOT_PATH . '/config/sql-resources.php';
            $settings = is_file($configFile) ? require $configFile : [];
        }
        if (!is_array($settings)
            || array_diff(array_keys($settings), ['root', 'exclude']) !== []) {
            throw new RuntimeException('Invalid SQL resource discovery settings.');
        }

        $resolvedRoot = realpath($root ?? ($settings['root'] ?? QUERY_PATH));
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('SQL resource root is unavailable.');
        }
        $excluded = $settings['exclude'] ?? ['system'];
        if (!is_array($excluded) || !array_is_list($excluded)) {
            throw new RuntimeException('Invalid SQL resource discovery exclusions.');
        }
        foreach ($excluded as $segment) {
            if (!$this->isSegment($segment)) {
                throw new RuntimeException('Invalid SQL resource discovery exclusions.');
            }
        }

        $this->root = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $this->excludedDirectories = array_map('strtolower', $excluded);
    }

    public function resolve(string $resource): array
    {
        if (!$this->isResourceId($resource)) {
            $this->invalidResource();
        }
        $resources = $this->resources();
        if (isset($resources[$resource])) {
            return $resources[$resource];
        }
        if (!str_contains($resource, '/')) {
            $matches = array_values(array_filter(
                $resources,
                fn (array $candidate): bool => $candidate['basename'] === $resource
            ));
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                throw new ApiRequestException(
                    'Ambiguous SQL resource.',
                    'INVALID_SQL_RESOURCE',
                    [['path' => 'resource', 'message' => 'Use the full relative resource identifier.']]
                );
            }
        }
        $this->invalidResource();
    }

    public function ids(): array
    {
        return array_keys($this->resources());
    }

    private function resources(): array
    {
        if ($this->resources !== null) {
            return $this->resources;
        }
        $resources = [];
        $canonicalIds = [];
        $physicalFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'sql') {
                continue;
            }
            $path = realpath($file->getPathname());
            if ($path === false || !$this->isInsideRoot($path)) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->root) + 1));
            $directory = dirname($relative);
            $directories = $directory === '.' ? [] : explode('/', $directory);
            if ($this->isExcluded($directories)) {
                continue;
            }
            $id = substr($relative, 0, -4);
            if (!$this->isResourceId($id)) {
                continue;
            }
            $canonical = strtolower($id);
            if (isset($canonicalIds[$canonical]) || isset($physicalFiles[$path])) {
                throw new RuntimeException("Duplicate SQL resource identity: {$id}");
            }
            $canonicalIds[$canonical] = true;
            $physicalFiles[$path] = true;
            $resources[$id] = ['id' => $id, 'basename' => basename($id), 'file' => $path];
        }
        ksort($resources, SORT_STRING);
        return $this->resources = $resources;
    }

    private function isExcluded(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')
                || in_array(strtolower($segment), $this->excludedDirectories, true)) {
                return true;
            }
        }
        return false;
    }

    private function isInsideRoot(string $path): bool
    {
        return str_starts_with($path, $this->root . DIRECTORY_SEPARATOR);
    }

    private function isResourceId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\/[A-Za-z0-9][A-Za-z0-9_-]*)*$/', $value) === 1;
    }

    private function isSegment($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $value) === 1;
    }

    private function invalidResource(): never
    {
        throw new ApiRequestException(
            'Invalid SQL resource.',
            'INVALID_SQL_RESOURCE',
            [['path' => 'resource', 'message' => 'Resource is not available.']]
        );
    }
}
