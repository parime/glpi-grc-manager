<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Policy;

use GlpiPlugin\Grcmanager\Services\Policy\PolicyLifecycle;
use PHPUnit\Framework\TestCase;

final class PolicyLifecycleTest extends TestCase
{
    public function testAllThreeStatusesAreValid(): void
    {
        self::assertTrue(PolicyLifecycle::isValid('draft'));
        self::assertTrue(PolicyLifecycle::isValid('approved'));
        self::assertTrue(PolicyLifecycle::isValid('archived'));
    }

    public function testUnknownOrMissingStatusIsInvalid(): void
    {
        self::assertFalse(PolicyLifecycle::isValid('under_review'));
        self::assertFalse(PolicyLifecycle::isValid(''));
        self::assertFalse(PolicyLifecycle::isValid(null));
    }

    public function testSanitizeKeepsAValidStatusUnchanged(): void
    {
        self::assertSame('approved', PolicyLifecycle::sanitize('approved'));
        self::assertSame('archived', PolicyLifecycle::sanitize('archived'));
    }

    public function testSanitizeFallsBackToDraftForAnInvalidValue(): void
    {
        self::assertSame('draft', PolicyLifecycle::sanitize('bogus'));
        self::assertSame('draft', PolicyLifecycle::sanitize(null));
    }

    public function testOnlyApprovedRequiresAnApprovalDate(): void
    {
        self::assertTrue(PolicyLifecycle::requiresApprovalDate('approved'));
        self::assertFalse(PolicyLifecycle::requiresApprovalDate('draft'));
        self::assertFalse(PolicyLifecycle::requiresApprovalDate('archived'));
    }

    public function testApprovalDateMissingOnlyBlocksTheApprovedStatus(): void
    {
        self::assertTrue(PolicyLifecycle::isApprovalDateMissing('approved', null));
        self::assertTrue(PolicyLifecycle::isApprovalDateMissing('approved', ''));
        self::assertTrue(PolicyLifecycle::isApprovalDateMissing('approved', '   '));
        self::assertFalse(PolicyLifecycle::isApprovalDateMissing('approved', '2026-01-15'));
        self::assertFalse(PolicyLifecycle::isApprovalDateMissing('draft', null));
        self::assertFalse(PolicyLifecycle::isApprovalDateMissing('archived', null));
    }
}
