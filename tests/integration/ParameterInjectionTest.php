<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\ApiClient;
use Tests\Integration\Mocks\EnvClient;
use Tests\Integration\Mocks\NullableApiClient;
use Tests\Integration\Mocks\OptionalClient;

final class ParameterInjectionTest extends TestCase
{
    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBindArgumentsAreUsedWhenResolving(): void
    {
        $this->container->bind(ApiClient::class, args: [
            'apiKey' => 'bound-key',
            'apiUrl' => 'https://bound.example.com',
        ]);

        $client = $this->container->get(ApiClient::class);

        $this->assertSame('bound-key', $client->apiKey);
        $this->assertSame('https://bound.example.com', $client->apiUrl);
    }

    public function testTransientArgumentsOverrideBinding(): void
    {
        $this->container->bind(ApiClient::class, args: [
            'apiKey' => 'bound-key',
            'apiUrl' => 'https://bound.example.com',
        ]);

        $client = $this->container->get(ApiClient::class, [
            'apiKey' => 'override-key',
            'apiUrl' => 'https://override.example.com',
        ]);

        $this->assertSame('override-key', $client->apiKey);
        $this->assertSame('https://override.example.com', $client->apiUrl);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testManualRegistryLookupUsedInBinding(): void
    {
        $params = $this->container->getParameters();

        $params->set('apiKey', 'registry-key');
        $params->set('apiUrl', 'https://registry.example.com');

        $this->container->bind(ApiClient::class, args: [
            'apiKey' => $params->get('apiKey'),
            'apiUrl' => $params->get('apiUrl'),
        ]);

        $client = $this->container->get(ApiClient::class);

        $this->assertSame('registry-key', $client->apiKey);
        $this->assertSame('https://registry.example.com', $client->apiUrl);
    }

    public function testBindUsesParameterRegistryValues(): void
    {
        $params = $this->container->getParameters();
        $params->set('apiKey', 'reg-key');
        $params->set('apiUrl', 'https://reg.example.com');

        $this->container->bind(ApiClient::class, args: [
            'apiKey' => $params->get('apiKey'),
            'apiUrl' => $params->get('apiUrl'),
        ]);

        $client = $this->container->get(ApiClient::class);

        $this->assertSame('reg-key', $client->apiKey);
        $this->assertSame('https://reg.example.com', $client->apiUrl);
    }

    public function testNullableParametersGetNull(): void
    {
        $client = $this->container->get(NullableApiClient::class);

        $this->assertNull($client->apiKey);
        $this->assertNull($client->apiUrl);
    }

    public function testOptionalParametersUseDefaults(): void
    {
        $client = $this->container->get(OptionalClient::class);

        $this->assertSame('default-key', $client->apiKey);
        $this->assertSame('https://default.com', $client->apiUrl);
    }

    public function testMissingPrimitiveThrows(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->get(ApiClient::class);
    }
}
