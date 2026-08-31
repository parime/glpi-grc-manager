<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Objective;

use GlpiPlugin\Grcmanager\Services\Objective\ObjectiveStatuses;
use PHPUnit\Framework\TestCase;

final class ObjectiveStatusesTest extends TestCase
{
    public function testAllFiveDocumentedStatusesAreValid(): void
    {
        self::assertTrue(ObjectiveStatuses::isValid('not_started'));
        self::assertTrue(ObjectiveStatuses::isValid('on_track'));
        self::assertTrue(ObjectiveStatuses::isValid('at_risk'));
        self::assertTrue(ObjectiveStatuses::isValid('achieved'));
        self::assertTrue(ObjectiveStatuses::isValid('missed'));
    }

    public function testInvalidOrMissingStatusesAreRejected(): void
    {
        self::assertFalse(ObjectiveStatuses::isValid(null));
        self::assertFalse(ObjectiveStatuses::isValid(''));
        self::assertFalse(ObjectiveStatuses::isValid('done'));
        self::assertFalse(ObjectiveStatuses::isValid('ON_TRACK'));
    }

    public function testAchievedAndMissedAreTheOnlyTerminalStatuses(): void
    {
        self::assertTrue(ObjectiveStatuses::isTerminal('achieved'));
        self::assertTrue(ObjectiveStatuses::isTerminal('missed'));

        self::assertFalse(ObjectiveStatuses::isTerminal('not_started'));
        self::assertFalse(ObjectiveStatuses::isTerminal('on_track'));
        self::assertFalse(ObjectiveStatuses::isTerminal('at_risk'));
        self::assertFalse(ObjectiveStatuses::isTerminal(null));
    }
}
