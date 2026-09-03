<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Risk;

use GlpiPlugin\Grcmanager\Services\Risk\OverdueTreatmentActionResult;
use PHPUnit\Framework\TestCase;

final class OverdueTreatmentActionResultTest extends TestCase
{
    public function testExposesDueAndNotifiedCounts(): void
    {
        $result = new OverdueTreatmentActionResult(5, 3);

        self::assertSame(5, $result->getDue());
        self::assertSame(3, $result->getNotified());
    }

    public function testZeroDueMeansZeroNotified(): void
    {
        $result = new OverdueTreatmentActionResult(0, 0);

        self::assertSame(0, $result->getDue());
        self::assertSame(0, $result->getNotified());
    }
}
