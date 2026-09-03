<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Objective;

use GlpiPlugin\Grcmanager\Services\Objective\ObjectiveMeasurementValidator;
use PHPUnit\Framework\TestCase;

final class ObjectiveMeasurementValidatorTest extends TestCase
{
    public function testQuantitativeObjectiveRequiresANumericValue(): void
    {
        self::assertTrue(ObjectiveMeasurementValidator::isValid(true, 42.0, ''));
        self::assertTrue(ObjectiveMeasurementValidator::isValid(true, 0.0, ''));
    }

    /**
     * A quantitative objective (target_value set) has a real number to plot the trajectory
     * against: a value-less measurement carries nothing to plot, rejected even with a comment.
     */
    public function testQuantitativeObjectiveRejectsAMissingValueEvenWithAComment(): void
    {
        self::assertFalse(ObjectiveMeasurementValidator::isValid(true, null, 'Progression en cours'));
        self::assertFalse(ObjectiveMeasurementValidator::isValid(true, null, ''));
    }

    /**
     * A qualitative-only objective has no number to compare against: a comment alone is a valid
     * measurement.
     */
    public function testQualitativeObjectiveAcceptsACommentWithoutAValue(): void
    {
        self::assertTrue(ObjectiveMeasurementValidator::isValid(false, null, 'MFA déployé sur 80% des comptes.'));
    }

    /**
     * A qualitative-only objective still accepts an optional numeric value if the reviewer has one
     * (e.g. a secondary indicator), it just is not required.
     */
    public function testQualitativeObjectiveAcceptsAValueWithoutAComment(): void
    {
        self::assertTrue(ObjectiveMeasurementValidator::isValid(false, 12.5, ''));
    }

    /**
     * A measurement recording literally nothing (no value, no comment) is never valid, whether the
     * objective is quantitative or qualitative.
     */
    public function testQualitativeObjectiveRejectsBothMissingValueAndEmptyComment(): void
    {
        self::assertFalse(ObjectiveMeasurementValidator::isValid(false, null, ''));
        self::assertFalse(ObjectiveMeasurementValidator::isValid(false, null, '   '));
    }
}
