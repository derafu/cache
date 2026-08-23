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

use Derafu\Cache\CacheWarmer;
use Derafu\Cache\Contract\WarmableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CacheWarmer::class)]
class CacheWarmerTest extends TestCase
{
    private CacheWarmer $warmer;

    protected function setUp(): void
    {
        $this->warmer = new CacheWarmer();
    }

    public function testReturnsNoFailuresWhenEveryWarmableSucceeds(): void
    {
        $calls = [];
        $a = $this->createWarmable(function () use (&$calls) {
            $calls[] = 'a';
        });
        $b = $this->createWarmable(function () use (&$calls) {
            $calls[] = 'b';
        });

        $failures = $this->warmer->warmup([$a, $b]);

        $this->assertSame([], $failures);
        $this->assertSame(['a', 'b'], $calls);
    }

    public function testContinuesAfterAFailingWarmableInsteadOfStopping(): void
    {
        $calls = [];
        $failing = $this->createWarmable(function () {
            throw new RuntimeException('boom');
        });
        $after = $this->createWarmable(function () use (&$calls) {
            $calls[] = 'after';
        });

        $failures = $this->warmer->warmup([$failing, $after]);

        // The one after the failure still ran.
        $this->assertSame(['after'], $calls);
        $this->assertCount(1, $failures);
    }

    public function testReportsWhichWarmableFailedAndWhy(): void
    {
        $exception = new RuntimeException('boom');
        $failing = $this->createWarmable(function () use ($exception) {
            throw $exception;
        });

        $failures = $this->warmer->warmup([$failing]);

        $this->assertSame($failing, $failures[0]['warmable']);
        $this->assertSame($exception, $failures[0]['exception']);
    }

    private function createWarmable(callable $onWarmup): WarmableInterface
    {
        return new class ($onWarmup) implements WarmableInterface {
            public function __construct(
                private readonly \Closure $onWarmup,
            ) {
            }

            public function warmup(): void
            {
                ($this->onWarmup)();
            }
        };
    }
}
