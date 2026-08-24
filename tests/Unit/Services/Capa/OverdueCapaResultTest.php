<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Capa;

use GlpiPlugin\Grcmanager\Services\Capa\OverdueCapaResult;
use PHPUnit\Framework\TestCase;

final class OverdueCapaResultTest extends TestCase
{
    public function testExposesDueAndNotifiedCounts(): void
    {
        $result = new OverdueCapaResult(5, 3);

        self::assertSame(5, $result->getDue());
        self::assertSame(3, $result->getNotified());
    }

    public function testZeroDueMeansZeroNotified(): void
    {
        $result = new OverdueCapaResult(0, 0);

        self::assertSame(0, $result->getDue());
        self::assertSame(0, $result->getNotified());
    }
}
