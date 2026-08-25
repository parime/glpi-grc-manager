<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Training;

use GlpiPlugin\Grcmanager\Services\Training\TrainingRenewalResult;
use PHPUnit\Framework\TestCase;

final class TrainingRenewalResultTest extends TestCase
{
    public function testExposesDueAndNotifiedCounts(): void
    {
        $result = new TrainingRenewalResult(4, 2);

        self::assertSame(4, $result->getDue());
        self::assertSame(2, $result->getNotified());
    }

    public function testZeroDueMeansZeroNotified(): void
    {
        $result = new TrainingRenewalResult(0, 0);

        self::assertSame(0, $result->getDue());
        self::assertSame(0, $result->getNotified());
    }
}
