<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 11 variant (untyped) of CommonGLPI::$rightname (GLPI 12: static string). Value: the using
 * class's RIGHTNAME constant.
 *
 * Loaded by src/Compatibility/HasRightname.php, never directly.
 */
trait HasRightname
{
    public static $rightname = self::RIGHTNAME;
}
