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

use Derafu\Cache\Contract\CacheKeyInterface;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Cache\LocalCacheFactory;
use Derafu\Cache\Translation\CacheTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Exercises the production key service with consumer-defined pools and configuration.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(?string $fixture = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $root = dirname(__DIR__, 2);
        (new YamlFileLoader($container, new FileLocator([
            $root . '/resources/config',
            $root . '/tests/fixtures/config',
        ])))
            ->load($fixture ?? 'cache-services.yaml');

        return $container;
    }

    public function testTheConfiguredProviderCanBeConstructed(): void
    {
        $container = $this->load();
        $container->getDefinition(CacheTranslationResourceProvider::class)->setPublic(true);
        $container->compile();
        $provider = $container->get(CacheTranslationResourceProvider::class);
        $this->assertInstanceOf(CacheTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateARealCacheConfigurationException(): void
    {
        $container = $this->load('translation.yaml');
        $this->assertArrayHasKey(
            CacheTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $container->getDefinition('app.translator')->setPublic(true);
        $container->compile();
        $translator = $container->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);

        // The application chooses its pool independently of the key service.
        try {
            LocalCacheFactory::pool(LocalCacheBackend::Filesystem, 'services-test');
            $this->fail('A filesystem cache without a directory was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('A directory is required for the "filesystem" cache backend.', $exception->getMessage());
            $this->assertSame('Se requiere un directorio para el backend de caché "filesystem".', $exception->trans($translator));
        }
    }

    public function testBaseConfigurationProvidesKeysWithoutChoosingAPool(): void
    {
        $container = $this->load();
        $container->getDefinition(CacheKeyInterface::class)->setPublic(true);
        $container->compile();
        $keys = $container->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $keys);
        $this->assertSame('document.App.Entity.Invoice', $keys->build('document', 'App\\Entity\\Invoice'));
        $this->assertFalse($container->has(CacheItemPoolInterface::class));
        $this->assertFalse($container->has(CacheInterface::class));
    }

    public function testConfiguredKeysCanStoreAndRetrieveValuesInAConsumerPool(): void
    {
        $container = $this->load('consumer.yaml');
        $container->getDefinition(CacheKeyInterface::class)->setPublic(true);
        $container->getDefinition(CacheItemPoolInterface::class)->setPublic(true);
        $container->compile();
        $keys = $container->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $keys);
        $pool = $container->get(CacheItemPoolInterface::class);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);

        $key = $keys->build('document', 'App\\Entity\\Invoice', ['status' => 'paid']);
        $item = $pool->getItem($key);
        $this->assertFalse($item->isHit());
        $this->assertTrue($pool->save($item->set(['total' => 100])));
        $stored = $pool->getItem($keys->build('document', 'App\\Entity\\Invoice', ['status' => 'paid']));
        $this->assertTrue($stored->isHit());
        $this->assertSame(['total' => 100], $stored->get());
        $this->assertFalse($pool->getItem($keys->build('document', 'App\\Entity\\Invoice', ['status' => 'draft']))->isHit());
    }

    public function testApplicationLengthOverrideReachesTheKeyService(): void
    {
        $defaultContainer = $this->load();
        $defaultContainer->getDefinition(CacheKeyInterface::class)->setPublic(true);
        $defaultContainer->compile();
        $defaultKeys = $defaultContainer->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $defaultKeys);
        $part = str_repeat('x', 90);
        $this->assertSame('document.' . $part, $defaultKeys->build('document', $part));

        $container = $this->load('short-keys.yaml');
        $container->getDefinition(CacheKeyInterface::class)->setPublic(true);
        $container->getDefinition(CacheItemPoolInterface::class)->setPublic(true);
        $container->compile();
        $keys = $container->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $keys);
        $pool = $container->get(CacheItemPoolInterface::class);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $key = $keys->build('document', $part);
        $this->assertSame('document.' . md5('document.' . $part), $key);
        $this->assertLessThanOrEqual(64, strlen($key));
        $this->assertTrue($pool->save($pool->getItem($key)->set('cached')));
        $this->assertSame('cached', $pool->getItem($keys->build('document', $part))->get());
    }
}
