<?php

/**
 * -------------------------------------------------------------------------
 * GLPI GRC Manager plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-grc-manager
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

/**
 * GLPI notification target for PluginGrcmanagerTraining (Sprint 6 training renewal reminders, see
 * src/Services/Training/TrainingRenewalService.php and its Cron entry point
 * PluginGrcmanagerTraining::cronRenewaldue()). Class name/file location follow the exact same GLPI
 * core naming convention already confirmed live for every other NotificationTarget in this plugin,
 * see inc/notificationtargetrisk.class.php's own docblock.
 *
 * Unlike every other NotificationTarget in this plugin (which resolve a single recipient from the
 * item's own `users_id` "owner" field via Notification::ITEM_USER), a training has no single
 * owner: it has a set of participants, of which zero, one or several can be overdue for renewal
 * at once. addAdditionalTargets() below resolves that set explicitly (same technique GLPI core
 * itself uses for a Ticket's multiple assigned technicians/observers) rather than relying on the
 * generic item-owner target, and calls addTarget() once per overdue participant so a single
 * training with several overdue participants sends one notification to every one of them.
 */
class PluginGrcmanagerNotificationTargetTraining extends NotificationTarget
{
    #[Override]
    public function getEvents()
    {
        return [
            PluginGrcmanagerTraining::RENEWAL_DUE_EVENT => __(
                'Renouvellement de formation en retard',
                'grcmanager'
            ),
        ];
    }

    #[Override]
    public function addAdditionalTargets($event = '')
    {
        $trainingId = (int) ($this->obj->fields['id'] ?? 0);

        if ($trainingId <= 0) {
            return;
        }

        foreach (PluginGrcmanagerTraining::getOverdueParticipants($trainingId) as $userId => $name) {
            $this->addTarget($userId, sprintf(__('Participant : %s', 'grcmanager'), $name));
        }
    }

    #[Override]
    public function addDataForTemplate($event, $options = [])
    {
        $events   = $this->getAllEvents();
        $training = $this->obj;

        $formats = PluginGrcmanagerTraining::getFormats();

        $this->data['##training.action##'] = $events[$event] ?? '';
        $this->data['##training.title##']  = $training->fields['title'] ?? '';
        $this->data['##training.format##'] = $formats[$training->fields['format'] ?? ''] ?? '';
        $this->data['##training.renewalmonths##'] = (string) (
            $training->fields['renewal_period_months'] ?? 0
        );
        $this->data['##training.url##'] = $this->formatURL(
            $options['additionnaloption']['usertype'] ?? self::GLPI_USER,
            PluginGrcmanagerTraining::class . '_' . $training->getID()
        );

        $this->getTags();
        foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    #[Override]
    public function getTags()
    {
        $tags = [
            'training.action'        => _n('Event', 'Events', 1),
            'training.title'         => __('Titre', 'grcmanager'),
            'training.format'        => __('Format', 'grcmanager'),
            'training.renewalmonths' => __('Renouvellement (mois)', 'grcmanager'),
            'training.url'           => __('URL'),
        ];

        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => true]);
        }

        asort($this->tag_descriptions);
    }
}
