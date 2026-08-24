<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Services\Risk;

/**
 * Pure probability x impact risk-scoring logic (ISO 27001 clause 6.1.2/8.2 generic risk
 * assessment), kept free of any GLPI runtime dependency so it can be unit-tested without a
 * running GLPI instance. Reused by PluginGrcmanagerRisk::prepareInputForAdd()/prepareInputForUpdate()
 * whenever probability/impact change, so `computed_score`/`risk_level` are never entered
 * manually and always stay consistent with the two source fields.
 *
 * Sprint 1 ships a fixed 4x4 matrix (hardcoded weights below); an administrable matrix (mirroring
 * the sibling plugin glpi-vulnerability-manager's own configurable probability x impact matrix)
 * is planned for a later sprint, see docs/design/DEVELOPMENT_PLAN.md.
 */
final class RiskScoringService
{
    /**
     * @var array<string, int>
     */
    private const PROBABILITY_WEIGHTS = [
        'rare'     => 1,
        'possible' => 2,
        'probable' => 3,
        'certain'  => 4,
    ];

    /**
     * @var array<string, int>
     */
    private const IMPACT_WEIGHTS = [
        'low'      => 1,
        'medium'   => 2,
        'high'     => 3,
        'critical' => 4,
    ];

    public function score(string $probability, string $impact): float
    {
        $probabilityWeight = self::PROBABILITY_WEIGHTS[$probability] ?? 0;
        $impactWeight       = self::IMPACT_WEIGHTS[$impact] ?? 0;

        return (float) ($probabilityWeight * $impactWeight);
    }

    /**
     * Maps a raw score (1-16, see score() above) onto the same low/medium/high/critical scale
     * already used for `impact`, so a single riskLevelBadge() can render both (see
     * inc/risk.class.php).
     */
    public function level(float $score): string
    {
        return match (true) {
            $score >= 12 => 'critical',
            $score >= 8  => 'high',
            $score >= 4  => 'medium',
            default      => 'low',
        };
    }
}
