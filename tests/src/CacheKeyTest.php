<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsCache;

use Derafu\Cache\CacheKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheKey::class)]
class CacheKeyTest extends TestCase
{
    private CacheKey $cacheKey;

    protected function setUp(): void
    {
        $this->cacheKey = new CacheKey();
    }

    public function testBuildsAKeyFromAPrefixAlone(): void
    {
        $this->assertSame('example', $this->cacheKey->build('example'));
    }

    public function testJoinsScalarPartsWithTheSamePrefix(): void
    {
        $this->assertSame(
            'example.folios.3',
            $this->cacheKey->build('example', 'folios', 3)
        );
    }

    public function testSanitizesForbiddenPsr6CharactersInsteadOfDroppingThem(): void
    {
        $key = $this->cacheKey->build('example', 'App\\Foo\\Bar');

        $this->assertSame('example.App.Foo.Bar', $key);
        $this->assertStringNotContainsString('\\', $key);
    }

    public function testDifferentClassNamesNeverCollideAfterSanitizing(): void
    {
        // A naive "drop forbidden characters" sanitizer would collide
        // App\FooBar with App\Foo\Bar (both becoming "AppFooBar"). This
        // one replaces with a separator instead, so they never do.
        $a = $this->cacheKey->build('example', 'App\\FooBar');
        $b = $this->cacheKey->build('example', 'App\\Foo\\Bar');

        $this->assertNotSame($a, $b);
    }

    public function testHashesNonScalarParts(): void
    {
        $key = $this->cacheKey->build('example', ['api_resource' => true]);

        $this->assertSame(
            'example.' . md5(serialize(['api_resource' => true])),
            $key
        );
    }

    public function testSameArrayAlwaysHashesToTheSameSegment(): void
    {
        $a = $this->cacheKey->build('example', ['x' => 1, 'y' => 2]);
        $b = $this->cacheKey->build('example', ['x' => 1, 'y' => 2]);

        $this->assertSame($a, $b);
    }

    public function testNullAndEmptyStringPartsAreSkipped(): void
    {
        $this->assertSame(
            'example.folios',
            $this->cacheKey->build('example', null, '', 'folios')
        );
    }

    public function testCollapsesToAHashWhenTheAssembledKeyExceedsMaxLength(): void
    {
        $cacheKey = new CacheKey(maxLength: 20);

        $key = $cacheKey->build('example', str_repeat('a', 100));

        $this->assertLessThanOrEqual(20 + strlen('example.') + 32, strlen($key));
        $this->assertStringStartsWith('example.', $key);
    }

    public function testStaysUnderTheDefaultMemcachedSafeLength(): void
    {
        $key = $this->cacheKey->build(
            'backbone_dispatcher',
            'App\\Very\\Long\\Namespace\\With\\Many\\Segments\\ExampleWorkerClassName',
            ['api_resource' => true, 'extra' => str_repeat('x', 300)],
        );

        $this->assertLessThanOrEqual(250, strlen($key));
    }
}
