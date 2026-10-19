<?php

namespace Ufo\Packages\Tests\Recipes;

use PHPUnit\Framework\TestCase;
use Ufo\Packages\Recipes\VersionTags;

class VersionTagsTest extends TestCase
{
    public function testCollapsesToSortedUniqueMinors(): void
    {
        $minors = VersionTags::minors(['10.0.1', '9.2.0', '9.2.1', '1.0.0', '10.0.0', '9.10.0']);

        $this->assertSame(['1.0', '9.2', '9.10', '10.0'], $minors);
    }

    public function testAcceptsVPrefixAndFourPartVersions(): void
    {
        $this->assertSame(['1.2', '3.4'], VersionTags::minors(['v1.2.3', '3.4.5.6']));
    }

    public function testAcceptsTwoPartVersions(): void
    {
        // Composer treats "5.2" as 5.2.0
        $this->assertSame(['4.5', '5.2'], VersionTags::minors(['5.2', '4.5.2']));
    }

    public function testSkipsMalformedTags(): void
    {
        $this->assertSame(['1.0'], VersionTags::minors(['1', 'latest', 'dev-main', '1.0.0', 'release-2.0']));
    }

    public function testSkipsPrereleasesUnlessAllowed(): void
    {
        $versions = ['1.0.0', '2.0.0-beta1', '2.0.0RC1', '3.0.0+build5'];

        $this->assertSame(['1.0', '3.0'], VersionTags::minors($versions));
        $this->assertSame(['1.0', '2.0', '3.0'], VersionTags::minors($versions, prerelease: true));
    }

    public function testMinVersion(): void
    {
        $this->assertSame(['4.5', '10.0'], VersionTags::minors(['4.0.0', '4.5.0', '10.0.0'], minVersion: '4.5'));
    }
}
