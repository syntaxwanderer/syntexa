<?php

declare(strict_types=1);

namespace Syntexa\Tests;

use PHPUnit\Framework\TestCase;
use Syntexa\Core\Application;
use Syntexa\Core\Request;
use Syntexa\Core\Response;
use Syntexa\Tests\TestClient;

/**
 * Base test case for web/HTTP tests
 * 
 * Similar to Symfony's WebTestCase, but designed for Swoole-based Syntexa framework.
 * Tests run without a real Swoole server - we directly call Application::handleRequest().
 */
abstract class WebTestCase extends TestCase
{
    protected ?Application $app = null;
    protected ?TestClient $client = null;

    /**
     * Creates a test client for making HTTP requests
     */
    protected function createClient(): TestClient
    {
        if ($this->client === null) {
            $this->app = $this->createApplication();
            $this->client = new TestClient($this->app);
        }

        return $this->client;
    }

    /**
     * Creates the Application instance for testing
     * 
     * Override this method to customize the application configuration
     */
    protected function createApplication(): Application
    {
        return new Application();
    }

    /**
     * Assert that the response is successful (status code 200-299)
     */
    protected function assertResponseIsSuccessful(Response $response, string $message = ''): void
    {
        $statusCode = $response->getStatusCode();
        $this->assertGreaterThanOrEqual(200, $statusCode, $message ?: "Expected successful response, got {$statusCode}");
        $this->assertLessThan(300, $statusCode, $message ?: "Expected successful response, got {$statusCode}");
    }

    /**
     * Assert that the response status code matches expected value
     */
    protected function assertResponseStatusCode(Response $response, int $expectedCode, string $message = ''): void
    {
        $actualCode = $response->getStatusCode();
        $this->assertEquals(
            $expectedCode,
            $actualCode,
            $message ?: "Expected status code {$expectedCode}, got {$actualCode}"
        );
    }

    /**
     * Assert that the response contains specific text
     */
    protected function assertResponseContains(Response $response, string $text, string $message = ''): void
    {
        $content = $response->getContent();
        $this->assertStringContainsString(
            $text,
            $content,
            $message ?: "Response does not contain expected text: {$text}"
        );
    }

    /**
     * Assert that the response header matches expected value
     */
    protected function assertResponseHeader(Response $response, string $headerName, string $expectedValue, string $message = ''): void
    {
        $headers = $response->getHeaders();
        $actualValue = $headers[$headerName] ?? null;
        $this->assertEquals(
            $expectedValue,
            $actualValue,
            $message ?: "Expected header {$headerName} to be {$expectedValue}, got " . ($actualValue ?? 'null')
        );
    }

    /**
     * Assert that the response is a redirect
     */
    protected function assertResponseRedirects(Response $response, ?string $expectedLocation = null, string $message = ''): void
    {
        $statusCode = $response->getStatusCode();
        $this->assertGreaterThanOrEqual(300, $statusCode, $message ?: "Expected redirect response, got {$statusCode}");
        $this->assertLessThan(400, $statusCode, $message ?: "Expected redirect response, got {$statusCode}");

        if ($expectedLocation !== null) {
            $headers = $response->getHeaders();
            $location = $headers['Location'] ?? null;
            $this->assertEquals(
                $expectedLocation,
                $location,
                $message ?: "Expected redirect to {$expectedLocation}, got " . ($location ?? 'null')
            );
        }
    }

    /**
     * Assert that the response is JSON and contains expected data
     */
    protected function assertResponseJson(Response $response, array $expectedData = null, string $message = ''): void
    {
        $headers = $response->getHeaders();
        $contentType = $headers['Content-Type'] ?? '';
        $this->assertStringContainsString(
            'application/json',
            $contentType,
            $message ?: "Expected JSON response, got Content-Type: {$contentType}"
        );

        if ($expectedData !== null) {
            $content = $response->getContent();
            $actualData = json_decode($content, true);
            $this->assertIsArray($actualData, $message ?: "Response is not valid JSON");
            $this->assertEquals($expectedData, $actualData, $message);
        }
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->app = null;
        parent::tearDown();
    }
}

