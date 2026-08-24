<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Risk;

use GlpiPlugin\Grcmanager\Services\Risk\RiskScoringService;
use PHPUnit\Framework\TestCase;

final class RiskScoringServiceTest extends TestCase
{
    private RiskScoringService $service;

    protected function setUp(): void
    {
        $this->service = new RiskScoringService();
    }

    public function testRareAndLowScoresToLow(): void
    {
        self::assertSame(1.0, $this->service->score('rare', 'low'));
        self::assertSame('low', $this->service->level($this->service->score('rare', 'low')));
    }

    public function testCertainAndCriticalScoresToCritical(): void
    {
        self::assertSame(16.0, $this->service->score('certain', 'critical'));
        self::assertSame('critical', $this->service->level($this->service->score('certain', 'critical')));
    }

    public function testPossibleAndMediumScoresToMedium(): void
    {
        self::assertSame(4.0, $this->service->score('possible', 'medium'));
        self::assertSame('medium', $this->service->level(4.0));
    }

    public function testProbableAndHighScoresToHigh(): void
    {
        self::assertSame(9.0, $this->service->score('probable', 'high'));
        self::assertSame('high', $this->service->level(9.0));
    }

    public function testUnknownValuesScoreToZeroAndLow(): void
    {
        self::assertSame(0.0, $this->service->score('unknown', 'unknown'));
        self::assertSame('low', $this->service->level(0.0));
    }

    /**
     * @dataProvider levelBoundaryProvider
     */
    public function testLevelBoundaries(float $score, string $expected): void
    {
        self::assertSame($expected, $this->service->level($score));
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function levelBoundaryProvider(): array
    {
        return [
            'just below medium'   => [3.9, 'low'],
            'medium lower bound'  => [4.0, 'medium'],
            'just below high'     => [7.9, 'medium'],
            'high lower bound'    => [8.0, 'high'],
            'just below critical' => [11.9, 'high'],
            'critical lower bound' => [12.0, 'critical'],
        ];
    }
}
