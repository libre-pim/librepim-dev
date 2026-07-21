<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\StorageUtilsBundle\Doctrine\DBAL\Platform;

use Doctrine\DBAL\Platforms\MySQL80Platform;

class MySQL84Platform extends MySQL80Platform
{
    /**
     * MySQL returns DATETIME(6) values with microseconds (e.g. "2026-06-02 15:52:16.000000")
     * and regular DATETIME values without (e.g. "2026-06-02 15:52:16"). When a value is
     * provided, the format is detected from it directly, since DBAL does not pass column
     * metadata to Type::convertToPHPValue.
     *
     * Value-based detection makes this platform safe on MySQL 8.0 too: 8.0 values never carry
     * microseconds, so the plain "Y-m-d H:i:s" format is returned, matching legacy behaviour.
     */
    public function getDateTimeFormatString(string $value = ''): string
    {
        return str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
    }
}
