<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsCache\Console;

use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\CacheDirectory;
use Derafu\Cache\Console\ClearLocalCacheCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ClearLocalCacheCommand::class)]
#[UsesClass(CacheDirectory::class)]
#[UsesClass(PhpFilesCache::class)]
class ClearLocalCacheCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu_cache_command_test_' . uniqid();
    }

    public function testDeletesTheDirectoryAndReportsHowManyFilesWereRemoved(): void
    {
        $pool = new PhpFilesCache('test', $this->directory);
        $item = $pool->getItem('example_key');
        $item->set('computed');
        $pool->save($item);

        $tester = new CommandTester(new ClearLocalCacheCommand());
        $tester->execute(['path' => $this->directory]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Deleted', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function testSucceedsOnAnAlreadyEmptyOrMissingDirectory(): void
    {
        $tester = new CommandTester(new ClearLocalCacheCommand());
        $tester->execute(['path' => $this->directory]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Deleted 0 file(s)', $tester->getDisplay());
    }
}
