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
use Derafu\Cache\Console\LocalCacheStatsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LocalCacheStatsCommand::class)]
#[UsesClass(CacheDirectory::class)]
#[UsesClass(PhpFilesCache::class)]
class LocalCacheStatsCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu_cache_command_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new CacheDirectory($this->directory))->clear();
    }

    public function testShowsFileCountAndSizeForARealCacheDirectory(): void
    {
        $pool = new PhpFilesCache('test', $this->directory);
        $item = $pool->getItem('example_key');
        $item->set('computed');
        $pool->save($item);

        $tester = new CommandTester(new LocalCacheStatsCommand());
        $tester->execute(['path' => $this->directory]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertMatchesRegularExpression('/\b1\b/', $tester->getDisplay());
    }

    /** @return array<string, array{int, string}> */
    public static function fileSizes(): array
    {
        return [
            'below one kilobyte' => [1023, '1023.00 B'],
            'one kilobyte' => [1024, '1.00 KB'],
            'fractional kilobytes' => [1536, '1.50 KB'],
            'one megabyte' => [1048576, '1.00 MB'],
            'fractional megabytes' => [1572864, '1.50 MB'],
        ];
    }

    #[DataProvider('fileSizes')]
    public function testFormatsTheSizeOfRealFiles(int $size, string $expected): void
    {
        mkdir($this->directory);
        $this->assertSame($size, file_put_contents($this->directory . '/entry', str_repeat('x', $size)));
        $tester = new CommandTester(new LocalCacheStatsCommand());
        $tester->execute(['path' => $this->directory]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testShowsZerosForAnEmptyOrMissingDirectory(): void
    {
        $tester = new CommandTester(new LocalCacheStatsCommand());
        $tester->execute(['path' => $this->directory]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('0.00 B', $tester->getDisplay());
    }
}
