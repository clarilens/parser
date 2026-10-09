<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use InvalidArgumentException;

/** Registry entries are declared pure by the registering application. */
final class OperationRegistry
{
    /** @var array<string, array{version: string, operation: \Closure(string): string}> */
    private array $operations = [];

    public function register(string $id, string $version, \Closure $operation): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $id) || trim($version) === '' || isset($this->operations[$id])) {
            throw new InvalidArgumentException('Invalid or duplicate operation.');
        }
        $this->operations[$id] = ['version' => $version, 'operation' => $operation];
    }

    public function require(string $id, string $version): void
    {
        if (($this->operations[$id]['version'] ?? null) !== $version) {
            throw new InvalidArgumentException('Missing or mismatched operation: ' . $id . '@' . $version);
        }
    }

    public function apply(string $id, string $version, string $input): string
    {
        $this->require($id, $version);
        return ($this->operations[$id]['operation'])($input);
    }

    public function fingerprint(): string
    {
        $versions = [];
        foreach ($this->operations as $id => $entry) {
            $versions[$id] = $entry['version'];
        }
        return CanonicalConfig::hash($versions);
    }
}
