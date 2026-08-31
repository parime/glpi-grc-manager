<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Policy;

use GlpiPlugin\Grcmanager\Services\Policy\PolicyReviewReminderWindow;
use PHPUnit\Framework\TestCase;

final class PolicyReviewReminderWindowTest extends TestCase
{
    public function testNoReviewDateIsNeverDue(): void
    {
        self::assertFalse(PolicyReviewReminderWindow::isDue('approved', null, '2026-01-01'));
        self::assertFalse(PolicyReviewReminderWindow::isDue('approved', '', '2026-01-01'));
    }

    public function testArchivedPolicyIsNeverDueEvenWithAnOverdueReviewDate(): void
    {
        self::assertFalse(PolicyReviewReminderWindow::isDue('archived', '2020-01-01', '2026-01-01'));
    }

    public function testDraftOrApprovedPolicyIsDueOnceOverdue(): void
    {
        self::assertTrue(PolicyReviewReminderWindow::isDue('draft', '2025-12-01', '2026-01-01'));
        self::assertTrue(PolicyReviewReminderWindow::isDue('approved', '2025-12-01', '2026-01-01'));
    }

    public function testDueExactlyOnTheReviewDateItself(): void
    {
        self::assertTrue(PolicyReviewReminderWindow::isDue('approved', '2026-01-01', '2026-01-01'));
    }

    public function testDueWithinTheThirtyDayReminderWindow(): void
    {
        self::assertTrue(PolicyReviewReminderWindow::isDue('approved', '2026-01-15', '2026-01-01'));
    }

    public function testNotYetDueBeyondTheReminderWindow(): void
    {
        self::assertFalse(PolicyReviewReminderWindow::isDue('approved', '2026-03-01', '2026-01-01'));
    }
}
