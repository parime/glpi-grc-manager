<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 12 variant (typed) of RuleCollection::$menu_option (GLPI 12: string). Value: the using
 * class's MENU_OPTION constant.
 *
 * Loaded by src/Compatibility/HasMenuOption.php, never directly.
 */
trait HasMenuOption
{
    public string $menu_option = self::MENU_OPTION;
}
