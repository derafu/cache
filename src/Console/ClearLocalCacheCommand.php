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
 * Deletes every file under a local cache directory, given only its path
 * — see `CacheDirectory`'s own docblock for why that's a real, separate
 * capability from `CacheItemPoolInterface::clear()`.
 *
 * Named `derafu:local-cache:clear`, not the more obvious `cache:clear` —
 * that name is already taken in any real Symfony full-stack app
 * (`symfony/framework-bundle` ships its own `cache:clear`, for the
 * framework's own internal cache). A generic name from a library meant to
 * be added into someone else's `Application` is a collision waiting to
 * happen; still want a shorter name in your own app? Rename it after
 * construction: `(new ClearLocalCacheCommand())->setName('cache:clear-local')`.
 *
 * Only for a *local* cache directory — a Redis- or Memcached-backed cache
 * needs no equivalent from this package: `CacheItemPoolInterface::clear()`
 * is already part of PSR-6 and works the same on every adapter, so
 * clearing one you built yourself is `$pool->clear()`, no wrapping
 * required. What a path-only command adds is specific to backends that
 * actually write to a directory.
 *
 * Not registered into any application automatically: this package has no
 * DI container of its own to register commands into. Add it to your own
 * `Symfony\Component\Console\Application` (or your framework's command
 * registration) if you want it — `$app->add(new ClearLocalCacheCommand())`.
 * Requires `symfony/console`, which this package only suggests, not
 * requires — nothing else in `derafu/cache` needs it.
 */
#[AsCommand(
    name: 'derafu:local-cache:clear',
    description: 'Deletes every file under a local cache directory.',
)]
final class ClearLocalCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'The cache directory to clear.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $path */
        $path = $input->getArgument('path');

        $deleted = (new CacheDirectory($path))->clear();

        $io->success(sprintf('Deleted %d file(s) from "%s".', $deleted, $path));

        return Command::SUCCESS;
    }
}
