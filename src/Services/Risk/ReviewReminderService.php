<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Services\Risk;

use NotificationEvent;
use PluginGrcmanagerRisk;

/**
 * Finds risks whose review date has passed or is approaching, and raises GLPI's native
 * NotificationEvent for each one (see inc/notificationtargetrisk.class.php for the
 * NotificationTarget, and src/Install/Installer.php for the seeded Notification/
 * NotificationTemplate rows). Driven by the daily Cron task
 * PluginGrcmanagerRisk::cronReviewreminder() (see issue tracked in
 * docs/design/DEVELOPMENT_PLAN.md, Sprint 2).
 *
 * NOTE: depends on GLPI's legacy global-namespace CommonDBTM class (PluginGrcmanagerRisk) and the
 * NotificationEvent core class, not unit-tested in isolation, same exclusion rationale as the
 * sibling plugin glpi-vulnerability-manager, see phpstan.neon.dist.
 */
final class ReviewReminderService
{
    /**
     * A risk is flagged from this many days before its review_date, and stays flagged for as
     * long as it remains overdue (no upper bound past the date); matches the dashboard card
     * risksPendingReviewCount()'s own "review_date <= today" definition of overdue, extended here
     * to also cover risks approaching their review date, not only those already past it.
     */
    private const REMINDER_WINDOW_DAYS = 30;

    public const EVENT = 'review_due';

    public function notify(): ReviewReminderResult
    {
        $risk = new PluginGrcmanagerRisk();

        $dueRisks = $risk->find([
            'status'      => ['<>', 'closed'],
            'review_date' => ['<=', date('Y-m-d', strtotime('+' . self::REMINDER_WINDOW_DAYS . ' days'))],
            ['NOT' => ['review_date' => null]],
        ]);

        $notified = 0;

        foreach ($dueRisks as $riskRow) {
            $item = new PluginGrcmanagerRisk();
            if (!$item->getFromDB((int) $riskRow['id'])) {
                continue;
            }

            if (NotificationEvent::raiseEvent(self::EVENT, $item)) {
                $notified++;
            }
        }

        return new ReviewReminderResult(count($dueRisks), $notified);
    }
}
