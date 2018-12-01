<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Status;

use Exception;
use TypeError;
use function serialize;
use function array_keys;
use function array_values;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use App\Services\Status\StatusService;
use App\Contracts\Services\Status\StatusOptions;
use App\Contracts\Services\Status\StatusCacheException;
use App\Contracts\Services\Status\StatusServiceProvider;
use App\Contracts\Services\Status\StatusProviderNotRegisteredException;

/**
 * Status service tests.
 *
 * @author    Brandon Clothier <brandon14125@gmail.com>
 *
 * @version   1.0.0
 *
 * @license   MIT
 * @copyright 2018
 *
 * @SuppressWarnings("ExcessiveClassLength")
 * @SuppressWarnings("TooManyMethods")
 * @SuppressWarnings("TooManyPublicMethods")
 */
class StatusTest extends TestCase
{
    /**
     * Whether to cache statuses.
     *
     * @var bool
     */
    private $cacheStatuses;

    /**
     * Cache time-to-live.
     *
     * @var int
     */
    private $cacheTtl;

    /**
     * Cache key.
     *
     * @var string
     */
    private $cacheKey;

    /**
     * Associative array of 'name' => {@link \App\Contracts\Services\Status\StatusServiceProvider}.
     *
     * @var array
     */
    private $providers;

    /**
     * Set up StatusService test.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->cacheStatuses = false;
        $this->cacheTtl = 30;
        $this->cacheKey = 'status';

        $this->providers = [
            'null_provider' => $this->createMock(StatusServiceProvider::class),
        ];
    }

    /**
     * Returns an {@link \App\Contracts\Services\Status\StatusOptions} instance.
     *
     * @return \App\Contracts\Services\Status\StatusOptions
     */
    protected function getConfig(): StatusOptions
    {
        return new StatusOptions(
            $this->cacheStatuses,
            $this->cacheTtl,
            $this->cacheKey
        );
    }

    /**
     * Test that service can return a list of registered providers.
     *
     * @return void
     */
    public function testReturnsProviders(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $providers = $instance->getProviders();

        $this::assertEquals(array_values($this->providers), $providers);
    }

    /**
     * Test that service can return a list of registered provider names.
     *
     * @return void
     */
    public function testReturnsProviderNames(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $providerNames = $instance->getProviderNames();

        $this::assertEquals(array_keys($this->providers), $providerNames);
    }

    /**
     * Test that service properly filters out invalid provided when class is constructed.
     *
     * @return void
     */
    public function testFiltersInvalidProvidersFromArrayUponConstruction(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            ['invalid_provider' => 'Obviously this is not a provider']
        );

        $providerNames = $instance->getProviderNames();

        // Should be no provider names registered since the one we provided was invalid.
        $this::assertEquals([], $providerNames);
    }

    /**
     * Test that the service throws an {@link \InvalidArgumentException} if trying to register
     * a provider with a key that already exists.
     *
     * @return void
     */
    public function testThrowsExceptionIfRegisteringAProviderWhenOneWithNameAlreadyExists(): void
    {
        // We expect an InvalidArgumentException to be thrown.
        $this->expectException(InvalidArgumentException::class);

        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        // Add the same provider twice.
        $instance->addProvider('provider', $this->createMock(StatusServiceProvider::class));
        $instance->addProvider('provider', $this->createMock(StatusServiceProvider::class));
    }

    /**
     * Test that the service throws an {@link \InvalidArgumentException} if you remove
     * a provider that does not exist.
     *
     * @return void
     */
    public function testThrowsExceptionIfRemovingProviderThatDoesNotExist(): void
    {
        // We expect an InvalidArgumentException to be thrown.
        $this->expectException(InvalidArgumentException::class);

        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        // No provider by that name exists.
        $instance->removeProvider('provider');
    }

    /**
     * Test that the service throws an {@link \InvalidArgumentException} if you want to cache statuses
     * but don't provided an {@link \Psr\SimpleCache\CacheInterface} implementation.
     *
     * @return void
     */
    public function testThrowsExceptionIfCacheEnabledWithNoImplementation(): void
    {
        // We expect an InvalidArgumentException to be thrown.
        $this->expectException(InvalidArgumentException::class);

        // We want to cache statuses, but provide a null cache implementation.
        $this->cacheStatuses = true;

        new StatusService(
            null,
            $this->getConfig(),
            $this->providers
        );
    }

    /**
     * Test that we can add a provider to the service.
     *
     * @return void
     */
    public function testRegistersANewProvider(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $result = $instance->addProvider('provider', $this->createMock(StatusServiceProvider::class));

        $this::assertEquals(true, $result);
    }

    /**
     * Test that we can remove a provider from the service.
     *
     * @return void
     */
    public function testRemovesAProvider(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->addProvider('provider', $this->createMock(StatusServiceProvider::class));

        $result = $instance->removeProvider('provider');

        $this::assertEquals(true, $result);
    }

    /**
     * Test the status service with the caching feature disabled when resolving all
     * providers.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusWithCacheDisabledAllProviders(): void
    {
        // Disable status caching.
        $this->cacheStatuses = false;

        // This will be our fixed status
        $status = ['status' => 'A-OK'];

        $cache = $this->createMock(CacheInterface::class);

        // The cache should not be used if it is disabled.
        $cache->expects($this::never())->method('has');
        $cache->expects($this::never())->method('get');
        $cache->expects($this::never())->method('set');

        // Tell mock provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus();

        // Assert the status returned is the status from our provider.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service checks the cache for a status if the service
     * is configured to do so.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testChecksCacheForStatusAllProviders(): void
    {
        // Cache the status.
        $this->cacheStatuses = true;

        // This will be our fixed status.
        $status = ['status' => 'OK'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called (once for the 'all' group and once for the mock provider) and they
        // both return false to mock no cache entries present.
        $cache->expects($this::exactly(2))
            ->method('has')
            ->withConsecutive([$this->cacheKey.'_all'], [$this->cacheKey.'_null_provider'])
            ->will($this::onConsecutiveCalls(false, false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');
        // With caching enabled, we should be able to call `set` to save the
        // status to the cache. This will be called for the 'all' group and for the mock provider its self.
        // Notice that in the call to save all (or multiple providers) into the cache, the status array contains
        // the keys with the provider names. Otherwise for a single provider, it is just the status.
        $cache->expects($this::exactly(2))
            ->method('set')
            ->withConsecutive(
                [
                    $this->cacheKey.'_null_provider',
                    serialize($status),
                    $this->cacheTtl,
                ],
                [
                    $this->cacheKey.'_all',
                    serialize(['null_provider' => $status]),
                    $this->cacheTtl,
                ]
            )
            ->will($this::onConsecutiveCalls(true, true));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus();

        // Assert the status returned is our fixed status..
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service will get the status from the providers if caching is enabled,
     * and there is a cache key but the actual call to resolve the value from cache fails (i.e
     *  call to {@link \Psr\SimpleCache\CacheInterface::get} returns false).
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusFromProviderIfCacheCheckFails(): void
    {
        // Cache the status.
        $this->cacheStatuses = true;

        $status = ['status' => 'A-OK'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache. Also it should also receive a call to has for the individual
        // provider because we mocked a failure to get the cache value.
        $cache->expects($this::exactly(2))
            ->method('has')
            ->withConsecutive([$this->cacheKey.'_all'], [$this->cacheKey.'_null_provider'])
            ->will($this::onConsecutiveCalls(true, true));
        // Simulate a failure to retrieve cached item by forcing get to return null on the all provider check.
        // Also the call to get the individual provider from cache with success.
        $cache->expects($this::exactly(2))
            ->method('get')
            ->withConsecutive([$this->cacheKey.'_all', null], [$this->cacheKey.'_null_provider', null])
            ->will($this::onConsecutiveCalls(null, serialize($status)));

        // With caching enabled, and a failure to resolve cached value, set should be called
        // once since the function continues on with its logic and the call to get the individual provider
        // status out of cache succeeds (thus not triggering a call to save it in cache).
        $cache->expects($this::once())
            ->method('set')
            ->with(
                $this->cacheKey.'_all',
                serialize(['null_provider' => $status]),
                $this->cacheTtl
            )
            ->will($this::returnValue(true));

        // Tell mocked provider not to expect the getStatus to be invoked as the second call to the cache
        // is mocked to succeed.
        $this->providers['null_provider']->expects($this::never())->method('getStatus');

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus();

        // Assert the status returned is the right status.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service will get the status from the cache if it is
     * present for all configured providers. This is to test with no cache entry at
     * the 'all' group level, but a cached provider entry.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusFromCacheIfPresentAllProvidersNoAllCache(): void
    {
        // Cache the statuses.
        $this->cacheStatuses = true;

        $status = ['status' => '10-4'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache. We mock the call to get the all providers cache to false
        // to force it to check the cache for each individual provider.
        $cache->expects($this::exactly(2))
            ->method('has')
            ->withConsecutive([$this->cacheKey.'_all'], [$this->cacheKey.'_null_provider'])
            ->will($this::onConsecutiveCalls(false, true));

        // If there is a cached status, `get` should be called and should
        // return our mock status.
        $cache->expects($this::once())
            ->method('get')
            ->with($this->cacheKey.'_null_provider', null)
            ->will($this::returnValue(serialize($status)));
        // With caching enabled, and a status present in the cache for the provider but not the 'all' group, we should
        // save that in the cache.
        $cache->expects($this::once())
            ->method('set')
            ->with(
                $this->cacheKey.'_all',
                serialize(['null_provider' => $status]),
                $this->cacheTtl
            )
            ->will($this::returnValue(true));

        // Assert the providers `getStatus` function is not called since it is being
        // retrieved from the cache.
        $this->providers['null_provider']->expects($this::never())->method('getStatus');

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus();

        // Assert the status returned is the right status.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service resolves the status with caching disabled.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusWithCacheDisabledSingleProvider(): void
    {
        // Disable the cache.
        $this->cacheStatuses = false;

        $status = ['status' => 'Meh'];

        $cache = $this->createMock(CacheInterface::class);

        // No cache methods should be called.
        $cache->expects($this::never())->method('has');
        $cache->expects($this::never())->method('get');
        $cache->expects($this::never())->method('set');

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())
            ->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus('null_provider');

        // Assert the status returned is right status.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service checks the cache for a status if the service
     * is configured to do so.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testChecksCacheForStatusSingleProvider(): void
    {
        // Cache the status.
        $this->cacheStatuses = true;

        $status = ['status' => '404 Bruh'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns false to mock the status not being in cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');

        $cache->expects($this::once())->method('set')->with(
            $this->cacheKey.'_null_provider',
            serialize($status),
            $this->cacheTtl
        )->will($this::returnValue(true));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())
            ->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus('null_provider');

        // Assert the status returned is right status.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service will get the status from the cache if it is
     * present.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusFromCacheIfPresentSingle(): void
    {
        // Cache the status.
        $this->cacheStatuses = true;

        $status = ['status' => 'It\'s all good!'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(true));
        // If there is a cached status, `get` should be called and should
        // return our mock latest status.
        $cache->expects($this::once())
            ->method('get')
            ->with($this->cacheKey.'_null_provider', null)
            ->will($this::returnValue(serialize($status)));
        // With caching enabled, and a status present in the cache, we shouldn't
        // call put to update the status.
        $cache->expects($this::never())->method('set');

        // Assert the providers `getStatus` function is not called since it is being
        // retrieved from the cache.
        $this->providers['null_provider']->expects($this::never())->method('getStatus');

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatus('null_provider');

        // Assert the status returned is right status.
        $this::assertEquals(['null_provider' => $status], $statusCall);
    }

    /**
     * Assert that the service resolves arrays of providers with the cache disabled.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusWithCacheDisabledArray(): void
    {
        // Add another provider.
        $this->providers['null_provider_2'] = $this->createMock(StatusServiceProvider::class);

        // Disable caching.
        $this->cacheStatuses = false;

        // This will be our fixed status.
        $status = ['status' => 'Alright, alright, alright'];
        // This will be second status.
        $statusTwo = ['status' => ['version' => 3.1415, 'description' => 'It\'s PI']];

        $cache = $this->createMock(CacheInterface::class);

        // No cache methods should be called.
        $cache->expects($this::never())->method('has');
        $cache->expects($this::never())->method('get');
        $cache->expects($this::never())->method('set');

        // Tell mocked provider to return the set statuses.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));
        $this->providers['null_provider_2']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($statusTwo));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatusByArray(['null_provider', 'null_provider_2']);

        // Assert the service returns both our provider statuses.
        $this::assertEquals(['null_provider' => $status, 'null_provider_2' => $statusTwo], $statusCall);
    }

    /**
     * Assert that the service checks the cache for a status if the service
     * is configured to do so for an array of providers.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testChecksCacheForStatusArray(): void
    {
        // Add another provider.
        $this->providers['null_provider_2'] = $this->createMock(StatusServiceProvider::class);

        // Cache the status.
        $this->cacheStatuses = true;

        // This will be our fixed status.
        $status = ['status' => 'Alright, alright, alright'];
        // This will be second status.
        $statusTwo = ['status' => ['version' => 3.1415, 'description' => 'It\'s PI']];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns false to mock the status not being in cache.
        // Second provider is not cached as well....
        $cache->expects($this::exactly(3))
            ->method('has')
            ->withConsecutive(
                [$this->cacheKey.'_null_provider_null_provider_2'],
                [$this->cacheKey.'_null_provider'],
                [$this->cacheKey.'_null_provider_2']
            )->will($this::onConsecutiveCalls(false, false, false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');

        // Should cache all providers and the group cache value.
        $cache->expects($this::exactly(3))
            ->method('set')
            ->withConsecutive(
                [
                    $this->cacheKey.'_null_provider',
                    serialize($status),
                    $this->cacheTtl,
                ],
                [
                    $this->cacheKey.'_null_provider_2',
                    serialize($statusTwo),
                    $this->cacheTtl,
                ],
                [
                    $this->cacheKey.'_null_provider_null_provider_2',
                    serialize(['null_provider' => $status, 'null_provider_2' => $statusTwo]),
                    $this->cacheTtl,
                ]
            )
            ->will($this::onConsecutiveCalls(true, true, true));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));
        $this->providers['null_provider_2']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($statusTwo));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatusByArray(['null_provider', 'null_provider_2']);

        // Assert the statuses returned are right.
        $this::assertEquals(['null_provider' => $status, 'null_provider_2' => $statusTwo], $statusCall);
    }

    /**
     * Assert that the service will get the status from the cache if it is
     * present for an array of providers.
     *
     * **NOTE:**
     * This test covers when the providers are cached and the group is not. Should write tests
     * covering when not all providers are cached, etc to fully cover every case of the caching system.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusFromCacheIfPresentArray(): void
    {
        // Add another provider.
        $this->providers['null_provider_2'] = $this->createMock(StatusServiceProvider::class);

        // Cache the status.
        $this->cacheStatuses = true;

        // This will be our fixed status.
        $status = ['status' => 'Alright, alright, alright'];
        // This will be second status.
        $statusTwo = ['status' => ['version' => 3.1415, 'description' => 'It\'s PI']];

        $cache = $this->createMock(CacheInterface::class);

        // Check for group cache key and return false.
        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache. Assuming all providers are cached.
        $cache->expects($this::exactly(3))
            ->method('has')
            ->withConsecutive(
                [$this->cacheKey.'_null_provider_null_provider_2'],
                [$this->cacheKey.'_null_provider'],
                [$this->cacheKey.'_null_provider_2']
            )->will($this::onConsecutiveCalls(false, true, true));

        // If there is a cached status, `get` should be called and should
        // return our mock statuses.
        $cache->expects($this::exactly(2))
            ->method('get')
            ->withConsecutive(
                [$this->cacheKey.'_null_provider', null],
                [$this->cacheKey.'_null_provider_2', null]
            )->will($this::onConsecutiveCalls(serialize($status), serialize($statusTwo)));

        // Should cache the group cache key.
        $cache->expects($this::once())
            ->method('set')
            ->with(
                $this->cacheKey.'_null_provider_null_provider_2',
                serialize(['null_provider' => $status, 'null_provider_2' => $statusTwo]),
                $this->cacheTtl
            )
            ->will($this::returnValue(true));

        // Assert the providers `getStatus` function is not called since it is being
        // retrieved from the cache.
        $this->providers['null_provider']->expects($this::never())
            ->method('getStatus');
        $this->providers['null_provider_2']->expects($this::never())
            ->method('getStatus');

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatusByArray(['null_provider', 'null_provider_2']);

        // Assert the statuses are right.
        $this::assertEquals(['null_provider' => $status, 'null_provider_2' => $statusTwo], $statusCall);
    }

    /**
     * Assert that the service will get the status from the cache if it is
     * present from an array of providers when the group of providers is cached.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testGetsStatusFromCacheIfPresentArrayGroupCached(): void
    {
        // Add another provider.
        $this->providers['null_provider_2'] = $this->createMock(StatusServiceProvider::class);

        // Cache the status.
        $this->cacheStatuses = true;

        // This will be our fixed status for both providers.
        $status = ['null_provider' => ['status' => 'It\'s over 9000'], 'null_provider_2' => ['status' => 'Sup?']];

        $cache = $this->createMock(CacheInterface::class);

        // Check for group cache key and return true. This should be only invocation of has.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider_null_provider_2')
            ->will($this::returnValue(true));
        $cache->expects($this::once())
            ->method('get')
            ->with($this->cacheKey.'_null_provider_null_provider_2')
            ->will($this::returnValue(serialize($status)));

        $cache->expects($this::never())->method('set');

        // Assert the providers `getStatus` function is not called since it is being
        // retrieved from the cache.
        $this->providers['null_provider']->expects($this::never())->method('getStatus');
        $this->providers['null_provider_2']->expects($this::never())->method('getStatus');

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $statusCall = $instance->getStatusByArray(['null_provider', 'null_provider_2']);

        // Assert the statuses is correct.
        $this::assertEquals($status, $statusCall);
    }

    /**
     * Assert that the service checks the cache for a status if the service
     * is configured to do so.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     *
     * @return void
     */
    public function testThrowsExceptionWhenCacheSaveFails(): void
    {
        // We expect service to throw a cache exception upon failure to persist to cache.
        $this->expectException(StatusCacheException::class);

        // Cache the statuses.
        $this->cacheStatuses = true;

        // This will be our fixed last modified status.
        $status = ['status' => 'OK'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called (once for the 'all' group and once for the mock provider) and they
        // both return false to mock no cache entries present.
        $cache->expects($this::exactly(2))
            ->method('has')
            ->withConsecutive([$this->cacheKey.'_all'], [$this->cacheKey.'_null_provider'])
            ->will($this::onConsecutiveCalls(false, false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');

        // Force cache set method to return false to indicate a failure with saving as per PSR-16 implementation details.
        $cache->expects($this::once())
            ->method('set')->with(
                $this->cacheKey.'_null_provider',
                serialize($status),
                $this->cacheTtl
            )->will($this::returnValue(false));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus();
    }

    /**
     * Test that we properly handle empty arrays.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testThrowsExceptionWhenGettingStatusForEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        // Try to resolve an empty array.
        $instance->getStatusByArray([]);
    }

    /**
     * Test handling an unregistered provider.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testThrowsExceptionWhenResolvingProviderThatDoesntExist(): void
    {
        // We expect an InvalidArgumentException to be thrown.
        $this->expectException(StatusProviderNotRegisteredException::class);

        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        // Try to resolve an invalid provider.
        $instance->getStatus('invalid_provider');
    }

    /**
     * Test handling an unregistered provider.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testThrowsExceptionWhenResolvingProviderThatDoesntExistArray(): void
    {
        // We expect an InvalidArgumentException to be thrown.
        $this->expectException(StatusProviderNotRegisteredException::class);

        $cache = $this->createMock(CacheInterface::class);

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        // Try to resolve an invalid provider.
        $instance->getStatusByArray(['invalid_provider']);
    }

    /**
     * Test we handle any cache exception from the PSR cache implementation on check and transform it into
     * an {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsCacheExceptionIntoStatusCacheExceptionOnHas(): void
    {
        $this->expectException(StatusCacheException::class);
        // Cache the status.
        $this->cacheStatuses = true;

        $cache = $this->createMock(CacheInterface::class);

        // Throw a mock PSR cache exception when checking the cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::throwException(new MockCacheException('Test exception')));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }

    /**
     * Test it transforms a throwable caught when checking the cache into an
     * {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsThrowableIntoStatusCacheExceptionOnHas(): void
    {
        $this->expectException(StatusCacheException::class);
        // Cache the status.
        $this->cacheStatuses = true;

        $cache = $this->createMock(CacheInterface::class);

        // Throw an error (to force a Throwable catch) when checking the cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::throwException(new TypeError('Test exception')));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }

    /**
     * Test we handle any cache exception from the PSR cache implementation on get and transform it into
     * an {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsCacheExceptionIntoStatusCacheExceptionOnGet(): void
    {
        $this->expectException(StatusCacheException::class);
        // Cache the status.
        $this->cacheStatuses = true;

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(true));
        // Throw a mock PSR cache exception when fetching from the cache.
        $cache->expects($this::once())
            ->method('get')
            ->with($this->cacheKey.'_null_provider', null)
            ->will($this::throwException(new MockCacheException('Test exception')));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }

    /**
     * Test we handle any throwable from the PSR cache implementation on get and transform it into
     * an {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsThrowableIntoStatusCacheExceptionOnGet(): void
    {
        $this->expectException(StatusCacheException::class);

        // Cache the status.
        $this->cacheStatuses = true;

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns true to mock that the status is already present in
        // the cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(true));
        // Throw an error (to force a Throwable catch) when fetching from the cache.
        $cache->expects($this::once())
            ->method('get')
            ->with($this->cacheKey.'_null_provider', null)
            ->will($this::throwException(new TypeError('Test exception')));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }

    /**
     * Test we handle any cache exception from the PSR cache implementation on save and transform it into
     * an {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsCacheExceptionIntoStatusCacheExceptionOnSave(): void
    {
        $this->expectException(StatusCacheException::class);

        // Cache the status.
        $this->cacheStatuses = true;

        $status = ['status' => 'This is fine...'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns false to mock the status not being in cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');

        // Throw a mock PSR cache exception when trying to save something in the cache.
        $cache->expects($this::once())->method('set')->with(
            $this->cacheKey.'_null_provider',
            serialize($status),
            $this->cacheTtl
        )->will($this::throwException(new MockCacheException('Test exception')));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }

    /**
     * Test we handle any throwable from the PSR cache implementation on save and transform it into
     * an {@link \App\Contracts\Services\Status\StatusCacheException}.
     *
     * @throws \App\Contracts\Services\Status\StatusCacheException
     * @throws \App\Contracts\Services\Status\StatusProviderNotRegisteredException
     *
     * @return void
     */
    public function testTransformsThrowableIntoStatusCacheExceptionOnSave(): void
    {
        $this->expectException(StatusCacheException::class);

        // Cache the status.
        $this->cacheStatuses = true;

        $status = ['status' => 'Okie Dokie'];

        $cache = $this->createMock(CacheInterface::class);

        // Assert that the cache `has` method is called with cache key and
        // it returns false to mock the status not being in cache.
        $cache->expects($this::once())
            ->method('has')
            ->with($this->cacheKey.'_null_provider')
            ->will($this::returnValue(false));
        // If there is no cached status, get should not be called.
        $cache->expects($this::never())->method('get');

        // Throw an error (to force catching a Throwable) when we call set to save the status.
        $cache->expects($this::once())->method('set')->with(
            $this->cacheKey.'_null_provider',
            serialize($status),
            $this->cacheTtl
        )->will($this::throwException(new TypeError('Test exception')));

        // Tell mocked provider to return the set status.
        $this->providers['null_provider']->expects($this::once())->method('getStatus')
            ->will($this::returnValue($status));

        $instance = new StatusService(
            $cache,
            $this->getConfig(),
            $this->providers
        );

        $instance->getStatus('null_provider');
    }
}

/**
 * Mock class that implements the PSR cache exception interface.
 *
 * @author    Brandon Clothier <brandon14125@gmail.com>
 *
 * @version   1.0.0
 *
 * @license   MIT
 * @copyright 2018
 */
class MockCacheException extends Exception implements CacheException
{
    // Intentionally left blank.
}