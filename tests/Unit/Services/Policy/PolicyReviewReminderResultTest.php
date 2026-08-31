<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Policy;

use GlpiPlugin\Grcmanager\Services\Policy\PolicyReviewReminderResult;
use PHPUnit\Framework\TestCase;

final class PolicyReviewReminderResultTest extends TestCase
{
    public function testExposesDueAndNotifiedCounts(): void
    {
        $result = new PolicyReviewReminderResult(3, 2);

        self::assertSame(3, $result->getDue());
        self::assertSame(2, $result->getNotified());
    }

    public function testZeroDueMeansZeroNotified(): void
    {
        $result = new PolicyReviewReminderResult(0, 0);

        self::assertSame(0, $result->getDue());
        self::assertSame(0, $result->getNotified());
    }
}
