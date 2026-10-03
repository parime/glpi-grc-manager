<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 11 variant (untyped) of CommonDBRelation::$checkItem_2_Rights (GLPI 12: static int).
 * Value: the using class's CHECK_ITEM_2_RIGHTS constant.
 *
 * Loaded by src/Compatibility/HasCheckItem2Rights.php, never directly.
 */
trait HasCheckItem2Rights
{
    public static $checkItem_2_Rights = self::CHECK_ITEM_2_RIGHTS;
}
