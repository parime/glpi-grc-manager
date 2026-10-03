<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 11 variant (untyped) of CommonDBChild::$itemtype/$items_id (GLPI 12: static string).
 * Values: the using class's ITEMTYPE and ITEMS_ID constants.
 *
 * Loaded by src/Compatibility/HasChildItemLink.php, never directly.
 */
trait HasChildItemLink
{
    public static $itemtype = self::ITEMTYPE;

    public static $items_id = self::ITEMS_ID;
}
