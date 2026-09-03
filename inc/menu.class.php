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

use GlpiPlugin\Grcmanager\Services\Dashboard\DefaultDashboardService;

/**
 * Simple ancre sans table ni CommonDBTM : ne sert qu'à porter le titre et l'icône du menu de
 * premier niveau dédié "GRC & Conformité" (voir setup.php). GLPI core (Html::generateMenuSession())
 * remplit `$menu[$category]['title']`/`['icon']` d'une clé de menu inconnue des sectors natifs avec
 * le premier élément de son tableau MENU_TOADD dont getMenuContent()/getIcon() fournissent une
 * valeur ; sans cette classe placée en tête du tableau, le titre du sector deviendrait celui du
 * premier écran fonctionnel (ex: "Risques") au lieu du nom du regroupement.
 */
class PluginGrcmanagerMenu extends CommonGLPI
{
    public static function getMenuName()
    {
        return __('GRC & Conformité', 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-shield-lock';
    }

    /**
     * Pointe vers le tableau de bord ISMS déjà existant plutôt que vers un écran de recherche :
     * cette classe ne porte aucune donnée propre, il n'y a donc rien d'autre de pertinent à
     * afficher pour son propre lien de menu.
     */
    public static function getMenuContent()
    {
        return [
            'title' => self::getMenuName(),
            'page'  => '/front/central.php?dashboard=' . DefaultDashboardService::KEY,
            'icon'  => self::getIcon(),
        ];
    }
}
