<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Control;

use GlpiPlugin\Grcmanager\Services\Control\ControlCatalogDefaults;
use PHPUnit\Framework\TestCase;

final class ControlCatalogDefaultsTest extends TestCase
{
    public function testCatalogHasExactly93Controls(): void
    {
        self::assertCount(93, ControlCatalogDefaults::CONTROLS);
    }

    public function testCodesAreUnique(): void
    {
        $codes = array_keys(ControlCatalogDefaults::CONTROLS);

        self::assertCount(count($codes), array_unique($codes));
    }

    /**
     * Matches the real ISO/IEC 27001:2022 Annex A breakdown: 37 organizational, 8 people,
     * 14 physical, 34 technological controls (37+8+14+34 = 93).
     */
    public function testThemeCountsMatchThePublishedStandard(): void
    {
        $countsByTheme = array_count_values(ControlCatalogDefaults::CONTROLS);

        self::assertSame(
            [
                'organizational' => 37,
                'people'         => 8,
                'physical'       => 14,
                'technological'  => 34,
            ],
            $countsByTheme
        );
    }

    public function testEveryCodeMatchesItsThemesAnnexLetter(): void
    {
        $annexLetterByTheme = [
            'organizational' => '5',
            'people'         => '6',
            'physical'       => '7',
            'technological'  => '8',
        ];

        foreach (ControlCatalogDefaults::CONTROLS as $code => $theme) {
            $matches = [];
            $matched = preg_match('/^A\.(\d)\.\d+$/', $code, $matches);

            self::assertSame(1, $matched, "Code $code should match the A.<n>.<n> pattern");
            self::assertSame($annexLetterByTheme[$theme], $matches[1], "Code $code should belong to theme $theme");
        }
    }
}
