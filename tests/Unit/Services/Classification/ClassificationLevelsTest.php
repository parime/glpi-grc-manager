<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Classification;

use GlpiPlugin\Grcmanager\Services\Classification\ClassificationLevels;
use PHPUnit\Framework\TestCase;

final class ClassificationLevelsTest extends TestCase
{
    public function testValidLevelsAreAccepted(): void
    {
        self::assertTrue(ClassificationLevels::isValid('low'));
        self::assertTrue(ClassificationLevels::isValid('medium'));
        self::assertTrue(ClassificationLevels::isValid('high'));
    }

    public function testInvalidOrMissingLevelsAreRejected(): void
    {
        self::assertFalse(ClassificationLevels::isValid(null));
        self::assertFalse(ClassificationLevels::isValid(''));
        self::assertFalse(ClassificationLevels::isValid('critical'));
        self::assertFalse(ClassificationLevels::isValid('LOW'));
    }

    public function testSanitizeKeepsAValidValue(): void
    {
        self::assertSame('high', ClassificationLevels::sanitize('high'));
    }

    /**
     * A tampered/forged POST value never gets stored: sanitize() falls back to null (mapped to the
     * "not set" empty string on the DB column by the caller), never to a level nobody chose.
     */
    public function testSanitizeRejectsAnInvalidValueRatherThanGuessingADefault(): void
    {
        self::assertNull(ClassificationLevels::sanitize('urgent'));
        self::assertNull(ClassificationLevels::sanitize(null));
        self::assertNull(ClassificationLevels::sanitize(''));
    }

    public function testNoRowAtAllIsNotClassified(): void
    {
        self::assertFalse(ClassificationLevels::isClassified(null));
    }

    /**
     * A row that exists but has all three axes empty (e.g. left over after every axis was cleared)
     * is equivalent to "not classified", same as no row at all.
     */
    public function testARowWithZeroAxesSetIsNotClassified(): void
    {
        $row = ['confidentiality' => '', 'integrity' => '', 'availability' => ''];

        self::assertFalse(ClassificationLevels::isClassified($row));
    }

    /**
     * Partial classification (issue #26: an asset may have only its confidentiality assessed so
     * far) is already a valid, "classified" state, not an all-or-nothing requirement.
     */
    public function testARowWithSomeAxesSetIsClassified(): void
    {
        $row = ['confidentiality' => 'high', 'integrity' => '', 'availability' => ''];

        self::assertTrue(ClassificationLevels::isClassified($row));
    }

    public function testARowWithAllThreeAxesSetIsClassified(): void
    {
        $row = ['confidentiality' => 'high', 'integrity' => 'high', 'availability' => 'medium'];

        self::assertTrue(ClassificationLevels::isClassified($row));
    }

    public function testHasHighAxisIsFalseForNoRowOrNoHighAxis(): void
    {
        $noHighAxis = ['confidentiality' => 'low', 'integrity' => 'medium', 'availability' => ''];

        self::assertFalse(ClassificationLevels::hasHighAxis(null));
        self::assertFalse(ClassificationLevels::hasHighAxis($noHighAxis));
    }

    public function testHasHighAxisIsTrueAsSoonAsOneAxisIsHigh(): void
    {
        $highConfidentiality = ['confidentiality' => 'high', 'integrity' => '', 'availability' => ''];
        $highAvailability    = ['confidentiality' => '', 'integrity' => '', 'availability' => 'high'];

        self::assertTrue(ClassificationLevels::hasHighAxis($highConfidentiality));
        self::assertTrue(ClassificationLevels::hasHighAxis($highAvailability));
    }
}
