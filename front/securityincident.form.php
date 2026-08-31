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

include('../../../inc/includes.php');

$item = new PluginGrcmanagerSecurityIncident();

if (isset($_POST['add'])) {
    Session::checkRight(PluginGrcmanagerSecurityIncident::$rightname, CREATE);
    $newID = $item->add($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(PluginGrcmanagerSecurityIncident::$rightname, DELETE);
    $item->delete($_POST);
    Html::redirect(PluginGrcmanagerSecurityIncident::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkRight(PluginGrcmanagerSecurityIncident::$rightname, PURGE);
    $item->delete($_POST, 1);
    Html::redirect(PluginGrcmanagerSecurityIncident::getSearchURL());
} elseif (isset($_POST['update'])) {
    Session::checkRight(PluginGrcmanagerSecurityIncident::$rightname, UPDATE);
    $item->update($_POST);
    Html::back();
} else {
    Session::checkRight(PluginGrcmanagerSecurityIncident::$rightname, READ);

    Html::header(
        PluginGrcmanagerSecurityIncident::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'tools',
        PluginGrcmanagerSecurityIncident::class
    );

    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $item->getFromDB($id);
    }

    $item->showForm($id);

    Html::footer();
}
