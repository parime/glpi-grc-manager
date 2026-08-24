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

$item = new PluginGrcmanagerControl();

if (isset($_POST['add'])) {
    Session::checkRight(PluginGrcmanagerControl::$rightname, CREATE);
    $newID = $item->add($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(PluginGrcmanagerControl::$rightname, DELETE);
    $item->delete($_POST);
    Html::redirect(PluginGrcmanagerControl::getSearchURL());
} elseif (isset($_POST['purge'])) {
    Session::checkRight(PluginGrcmanagerControl::$rightname, PURGE);
    $item->delete($_POST, 1);
    Html::redirect(PluginGrcmanagerControl::getSearchURL());
} elseif (isset($_POST['update'])) {
    Session::checkRight(PluginGrcmanagerControl::$rightname, UPDATE);
    $item->update($_POST);
    Html::back();
} else {
    Session::checkRight(PluginGrcmanagerControl::$rightname, READ);

    Html::header(
        PluginGrcmanagerControl::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'admin',
        PluginGrcmanagerControl::class
    );

    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $item->getFromDB($id);
    }

    $item->showForm($id);

    Html::footer();
}
