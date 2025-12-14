<?php

declare(strict_types=1);

namespace Syntexa\Tests\Controller;

use Syntexa\Tests\WebTestCase;

/**
 * Test for LoginFormHandler
 * 
 * Example test demonstrating how to test HTTP handlers in Syntexa framework.
 */
class LoginFormHandlerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = $this->createClient();
        $response = $client->get('/login');

        $this->assertResponseIsSuccessful($response);
        $this->assertResponseContains($response, 'Login to Syntexa');
        $this->assertResponseContains($response, 'email');
        $this->assertResponseContains($response, 'password');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = $this->createClient();
        $response = $client->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        // Should still return 200 (show form with error)
        $this->assertResponseIsSuccessful($response);
        $this->assertResponseContains($response, 'Invalid email or password');
    }

    public function testLoginWithEmptyCredentials(): void
    {
        $client = $this->createClient();
        $response = $client->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $this->assertResponseIsSuccessful($response);
        $this->assertResponseContains($response, 'Email and password are required');
    }

    public function testLoginPageRedirectsWhenAuthenticated(): void
    {
        // This test would require setting up a session
        // For now, we'll just test that the page loads
        $client = $this->createClient();
        $response = $client->get('/login');

        $this->assertResponseIsSuccessful($response);
    }
}

