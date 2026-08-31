<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Tests\Services\Risk;

use GlpiPlugin\Grcmanager\Services\Risk\LinkableItemtypes;
use PHPUnit\Framework\TestCase;

final class LinkableItemtypesTest extends TestCase
{
    public function testDefaultListIsNotEmpty(): void
    {
        self::assertNotEmpty(LinkableItemtypes::DEFAULT_ITEMTYPES);
    }

    public function testDefaultListHasNoDuplicates(): void
    {
        self::assertCount(
            count(LinkableItemtypes::DEFAULT_ITEMTYPES),
            array_unique(LinkableItemtypes::DEFAULT_ITEMTYPES)
        );
    }

    /**
     * Covers the concrete examples cited in issue #25 ("Computer, Server actif personnalisé,
     * Software..."): Computer and Software must be part of the fixed default list.
     */
    public function testCoversTheExamplesCitedInTheIssue(): void
    {
        self::assertContains('Computer', LinkableItemtypes::DEFAULT_ITEMTYPES);
        self::assertContains('Software', LinkableItemtypes::DEFAULT_ITEMTYPES);
    }
}
