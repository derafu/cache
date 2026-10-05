<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Cache\Console;

use Derafu\Cache\CacheDirectory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows aggregate stats (file count, total size, oldest/newest entry) for
 * a local cache directory — see `CacheDirectory`'s own docblock: neither
 * PSR-6 nor `symfony/cache` expose this for any backend.
 *
 * Named `derafu:local-cache:stats`, namespaced under the package for the
 * same reason as `ClearLocalCacheCommand` — a generic `cache:stats` from
 * a library meant to be added into someone else's `Application` risks
 * colliding with whatever that app (or another bundle) already registered
 * under that name.
 *
 * Only meaningful for a local, filesystem-backed cache: "how many entries,
 * how much space" has no equivalent at all for Redis/Memcached, not even
 * one you could build yourself the way `$pool->clear()` covers clearing —
 * inspecting those requires their own admin tools (`redis-cli`,
 * `memcached-tool`), not something `derafu/cache` could wrap generically.
 *
 * Not registered into any application automatically — see
 * `ClearLocalCacheCommand`'s docblock, the same note applies here.
 *
 * The text of the command (its description, the help of its argument and the
 * output) is in English, like the one of the commands of Symfony: it is a
 * technical tool for whoever administers the application, and it is not
 * translated.
 */
#[AsCommand(
    name: 'derafu:local-cache:stats',
    description: 'Shows aggregate stats for a local cache directory.',
)]
final class LocalCacheStatsCommand extends Command
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    protected function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'The cache directory to inspect.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $path */
        $path = $input->getArgument('path');

        $stats = (new CacheDirectory($path))->stats();

        $io->table(
            ['Files', 'Total size', 'Oldest entry', 'Newest entry'],
            [[
                $stats['count'],
                $this->formatBytes($stats['totalSize']),
                $this->formatTimestamp($stats['oldest']),
                $this->formatTimestamp($stats['newest']),
            ]],
        );

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count(self::UNITS) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $value, self::UNITS[$unitIndex]);
    }

    private function formatTimestamp(?int $timestamp): string
    {
        return $timestamp === null ? '—' : date('Y-m-d H:i:s', $timestamp);
    }
}
