<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 * 
 * Prepares test environment:
 * - Loads Composer autoloader
 * - Ensures Docker containers are running (PostgreSQL, RabbitMQ)
 * - Sets up test environment variables
 */

// Load Composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Ensure test infrastructure is running (after autoloader is loaded)
// This allows us to use namespaced classes
use Syntexa\Tests\Examples\Orm\TestInfrastructureBootstrap;

TestInfrastructureBootstrap::ensureInfrastructure();

