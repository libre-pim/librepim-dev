<?php

declare(strict_types=1);

namespace Psr\Log\Test;

use Psr\Log\AbstractLogger;

/**
 * Minimal TestLogger implementation used in test environment when the psr/log Test classes
 * are not available as a dependency.
 */
class TestLogger extends AbstractLogger
{
    /** @var array<int, array{level: mixed, message: mixed, context: array}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function hasRecord(string $message, $level = null): bool
    {
        foreach ($this->records as $r) {
            if ($r['message'] === $message && (null === $level || $r['level'] === $level)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{level: mixed, message: mixed, context: array}> */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
