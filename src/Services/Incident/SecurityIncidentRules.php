<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Services\Incident;

/**
 * Pure decision logic behind PluginGrcmanagerSecurityIncident (issue #29, ISO/IEC 27001:2022
 * Annexe A A.5.24-27 "planification et préparation, évaluation et décision, réponse, apprentissage
 * des incidents de sécurité de l'information") : category/severity/status normalization, the
 * `cia_impact` comma-separated axis list, the optional zero-or-one link to a
 * PluginGrcmanagerRisk, the optional zero-or-one reference to a real GLPI Ticket/Problem, and the
 * root_cause/lessons_learned-required-before-closed validation (clause A.5.27). Kept
 * GLPI-independent (no $DB, no CommonDBTM, no __()) so every branch is unit tested directly, the
 * same split already used throughout this plugin between pure decision logic and the thin
 * CommonDBTM/$DB wrapper that calls it (see ComplianceObligationRules, ClassificationLevels,
 * CapaRequirementService).
 *
 * Not extracted into a shared trait/helper with ComplianceObligationRules::normalizeLinkedRiskId()/
 * isLinkedToRisk() despite the identical zero-or-one-risk-link logic: no other Rules class in this
 * plugin shares logic that way either (each registry owns a small, self-contained Rules class,
 * only RiskAssessmentTrait factors an actual shared *computation* across two classes that must
 * never diverge, see its own docblock) - duplicating five lines here is cheap and keeps this class
 * independently readable.
 */
final class SecurityIncidentRules
{
    /**
     * @var array<int, string>
     */
    public const ALLOWED_CATEGORIES = [
        'data_breach',
        'malware',
        'unauthorized_access',
        'availability',
        'other',
    ];

    public const DEFAULT_CATEGORY = 'other';

    /**
     * Same three-value scale as PluginGrcmanagerNonconformity::getSeverities() (issue #29: "reuse
     * the SAME severity scale"), so an organization never has to reconcile two differently-worded
     * severity scales across this plugin's registers.
     *
     * @var array<int, string>
     */
    public const ALLOWED_SEVERITIES = ['minor', 'major', 'critical'];

    public const DEFAULT_SEVERITY = 'minor';

    /**
     * @var array<int, string>
     */
    public const ALLOWED_STATUSES = ['open', 'investigating', 'contained', 'closed'];

    public const DEFAULT_STATUS = 'open';

    /**
     * A security incident is only ever linked to a real GLPI Ticket or Problem (issue #29: "cet
     * incident de sécurité correspond à ce Ticket/Problem GLPI"), never any other itemtype.
     *
     * @var array<int, string>
     */
    public const ALLOWED_LINKED_ITEMTYPES = ['Ticket', 'Problem'];

    /**
     * The three C/I/D axes an incident may have affected, reusing the exact same axis names as
     * GlpiPlugin\Grcmanager\Services\Classification\ClassificationLevels::AXES (issue #26) rather
     * than inventing a second confidentiality/integrity/availability vocabulary - not a literal
     * dependency on that class (this one stays free of any cross-namespace coupling, same
     * independence rationale as the class docblock above), just the same three string keys.
     *
     * @var array<int, string>
     */
    public const CIA_AXES = ['confidentiality', 'integrity', 'availability'];

    public static function normalizeCategory(?string $value): string
    {
        return in_array($value, self::ALLOWED_CATEGORIES, true) ? $value : self::DEFAULT_CATEGORY;
    }

    public static function normalizeSeverity(?string $value): string
    {
        return in_array($value, self::ALLOWED_SEVERITIES, true) ? $value : self::DEFAULT_SEVERITY;
    }

    public static function normalizeStatus(?string $value): string
    {
        return in_array($value, self::ALLOWED_STATUSES, true) ? $value : self::DEFAULT_STATUS;
    }

    /**
     * `cia_impact` is stored as a comma-separated list on a single varchar column, the same
     * "fixed-value list on one column" convention already established by
     * `PluginGrcmanagerAudit.risk_categories` (Sprint 4, TECH_DEBT.md) rather than a link table -
     * appropriate here for exactly the same reason (a small closed set of 3 values, never a
     * variable/unbounded target). Rendered in showForm() as plain HTML checkboxes rather than
     * `Dropdown::showFromArray(..., ['multiple' => true])` (the select2 widget
     * `PluginGrcmanagerAudit::showForm()` uses for `risk_categories`): a select2 multi-select
     * backed by a plain PHP array is known, in this plugin family's own live testing, to not
     * reliably persist a selection made via low-level JS-dispatched events, and for a fixed set of
     * only 3 checkboxes a select2 widget has no real benefit (no search, no long list to
     * scroll) - plain checkboxes are simpler AND more reliable here.
     *
     * @param array<int, string>|string|null $value Either an array of axis keys (checkbox
     *        `cia_impact[]` form submission) or an already-comma-joined string.
     * @return string Comma-separated list of valid axes, in canonical CIA_AXES order, never
     *         containing an unknown value; empty string if none/invalid.
     */
    public static function normalizeCiaImpact(array|string|null $value): string
    {
        $candidates = is_array($value)
            ? $value
            : array_map('trim', explode(',', (string) $value));

        $selected = array_intersect(self::CIA_AXES, $candidates);

        // array_intersect() above already preserves self::CIA_AXES's own order (it iterates the
        // first argument), kept explicit here rather than relying on that as an implementation
        // detail: re-filter through CIA_AXES to guarantee the canonical order regardless.
        $ordered = array_values(array_filter(
            self::CIA_AXES,
            static fn (string $axis): bool => in_array($axis, $selected, true)
        ));

        return implode(',', $ordered);
    }

    /**
     * @return array<int, string>
     */
    public static function splitCiaImpact(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /**
     * The optional link to a risk (issue #29: "lien optionnel vers un risque") is a direct column
     * (`plugin_grcmanager_risks_id`), not a link table - same zero-or-one cardinality and same
     * choice already made for PluginGrcmanagerComplianceObligation (issue #30, see its own
     * docblock and ComplianceObligationRules::normalizeLinkedRiskId()). 0 is the canonical "no risk
     * linked" value throughout this plugin's schema, never NULL.
     */
    public static function normalizeLinkedRiskId(int|string|null $value): int
    {
        $id = (int) $value;

        return $id > 0 ? $id : 0;
    }

    public static function isLinkedToRisk(int $riskId): bool
    {
        return $riskId > 0;
    }

    /**
     * The optional reference to a real GLPI Ticket/Problem (issue #29: "une relation légère 'cet
     * incident de sécurité correspond à ce Ticket/Problem GLPI'"), a zero-or-one polymorphic
     * reference restricted to ALLOWED_LINKED_ITEMTYPES - simpler cardinality than the risk<->CMDB
     * asset many-to-many link table of issue #25 (an incident maps to AT MOST ONE ticket/problem in
     * practice), so two direct columns (`linked_itemtype`/`linked_items_id`) rather than a link
     * table, same reasoning as the risk-link column above. Anything outside
     * ALLOWED_LINKED_ITEMTYPES, or a non-positive id, collapses to the canonical "unlinked" pair
     * (empty string, 0) rather than being stored as garbage.
     *
     * @return array{itemtype: string, items_id: int}
     */
    public static function normalizeLinkedItem(?string $itemtype, int|string|null $itemsId): array
    {
        $id = (int) $itemsId;

        if ($itemtype === null || !in_array($itemtype, self::ALLOWED_LINKED_ITEMTYPES, true) || $id <= 0) {
            return ['itemtype' => '', 'items_id' => 0];
        }

        return ['itemtype' => $itemtype, 'items_id' => $id];
    }

    public static function isLinkedToItem(?string $itemtype, int $itemsId): bool
    {
        return $itemtype !== null && $itemtype !== '' && $itemsId > 0;
    }

    /**
     * Enforces ISO/IEC 27001:2022 clause A.5.27 ("tirer des enseignements des incidents") in
     * practice: an incident cannot be marked `closed` without a documented root cause AND
     * documented lessons learned. Neither is required to open an incident or move it to
     * `investigating`/`contained` - the exact same "required only at a certain status transition"
     * convention already established by PluginGrcmanagerNonconformity's own
     * `corrective_action`/CapaRequirementService::isCapaMandatory() (issue #27, Sprint 4), applied
     * here to TWO fields instead of one because A.5.27 explicitly asks for both a cause and a
     * lesson, not just a fix.
     */
    public static function isClosureDocumentationMissing(
        string $status,
        ?string $rootCause,
        ?string $lessonsLearned
    ): bool {
        if ($status !== 'closed') {
            return false;
        }

        return trim((string) $rootCause) === '' || trim((string) $lessonsLearned) === '';
    }
}
