<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 11 variant (untyped) of CommonDBRelation::$itemtype_1/$items_id_1/$itemtype_2/$items_id_2
 * (GLPI 12: static ?string). Values: the using class's ITEMTYPE_1, ITEMS_ID_1, ITEMTYPE_2 and
 * ITEMS_ID_2 constants.
 *
 * Loaded by src/Compatibility/HasItilActorRelation.php, never directly.
 */
trait HasItilActorRelation
{
    public static $itemtype_1 = self::ITEMTYPE_1;

    public static $items_id_1 = self::ITEMS_ID_1;

    public static $itemtype_2 = self::ITEMTYPE_2;

    public static $items_id_2 = self::ITEMS_ID_2;
}
