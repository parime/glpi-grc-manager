<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 11 variant (untyped) of CommonDBTM::$dohistory/$usenotepad (GLPI 12: bool) and
 * CommonITILObject::$userlinkclass/$grouplinkclass/$supplierlinkclass (GLPI 12: string). Link
 * classes: the using class's USERLINKCLASS, GROUPLINKCLASS and SUPPLIERLINKCLASS constants;
 * history and notepad always enabled.
 *
 * Loaded by src/Compatibility/HasItilObjectLinks.php, never directly.
 */
trait HasItilObjectLinks
{
    public $dohistory = true;

    public $userlinkclass = self::USERLINKCLASS;

    public $grouplinkclass = self::GROUPLINKCLASS;

    public $supplierlinkclass = self::SUPPLIERLINKCLASS;

    protected $usenotepad = true;
}
