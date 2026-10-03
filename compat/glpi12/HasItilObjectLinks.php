<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Compatibility;

/**
 * GLPI 12 variant (typed) of CommonDBTM::$dohistory/$usenotepad (GLPI 12: bool) and
 * CommonITILObject::$userlinkclass/$grouplinkclass/$supplierlinkclass (GLPI 12: string). Link
 * classes: the using class's USERLINKCLASS, GROUPLINKCLASS and SUPPLIERLINKCLASS constants;
 * history and notepad always enabled.
 *
 * Loaded by src/Compatibility/HasItilObjectLinks.php, never directly.
 */
trait HasItilObjectLinks
{
    public bool $dohistory = true;

    public string $userlinkclass = self::USERLINKCLASS;

    public string $grouplinkclass = self::GROUPLINKCLASS;

    public string $supplierlinkclass = self::SUPPLIERLINKCLASS;

    protected bool $usenotepad = true;
}
