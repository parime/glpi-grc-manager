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
     * Pointe vers le registre des risques (premier écran attendu par un RSSI, voir setup.php),
     * pas vers le tableau de bord ISMS : `Glpi\Controller\CentralController` n'honore le paramètre
     * `dashboard` que combiné à `embed` (rendu anonyme sans en-tête/menu, cf. son propre code
     * source) - un lien `/front/central.php?dashboard=...` sans `embed` est silencieusement ignoré
     * et affiche le dernier tableau de bord "Central" utilisé, pas celui-ci (confirmé en réel :
     * atterrissait sur l'accueil au lieu du tableau de bord ISMS). Cette classe ne porte aucune
     * donnée propre, un lien vers un vrai écran du plugin reste plus utile qu'un lien mort.
     */
    public static function getMenuContent()
    {
        return [
            'title' => self::getMenuName(),
            'page'  => PluginGrcmanagerRisk::getSearchURL(false),
            'icon'  => self::getIcon(),
        ];
    }
}
