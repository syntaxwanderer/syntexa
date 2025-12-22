<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

/**
 * Blockchain Configuration
 * 
 * Loads configuration from environment variables.
 * Auto-enables distributed mode when multiple participants are configured.
 */
readonly class BlockchainConfig
{
    public function __construct(
        public bool $enabled,
        public ?string $dbHost = null,
        public ?int $dbPort = null,
        public ?string $dbName = null,
        public ?string $dbUser = null,
        public ?string $dbPassword = null,
        public ?array $participants = null,
        public ?string $nodeId = null,
        public ?string $rabbitmqHost = null,
        public ?int $rabbitmqPort = null,
        public ?string $rabbitmqUser = null,
        public ?string $rabbitmqPassword = null,
        public ?string $rabbitmqExchange = null,
        public ?string $rabbitmqVhost = null,
        public int $blockSize = 100,
        public int $blockTimeLimit = 10,
        public int $mempoolMaxSize = 10000,
        public int $proposerInterval = 5,
        public int $consensusTimeout = 30,
    ) {}

    /**
     * Create config from environment variables
     */
    public static function fromEnv(): self
    {
        $env = self::loadEnv();
        
        $enabled = filter_var($env['BLOCKCHAIN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $participants = array_filter(
            array_map('trim', explode(',', $env['BLOCKCHAIN_PARTICIPANTS'] ?? ''))
        );
        
        // Auto-enable if multiple participants configured
        if (!$enabled && count($participants) > 1) {
            $enabled = true;
        }
        
        return new self(
            enabled: $enabled,
            dbHost: $env['BLOCKCHAIN_DB_HOST'] ?? null,
            dbPort: isset($env['BLOCKCHAIN_DB_PORT']) ? (int) $env['BLOCKCHAIN_DB_PORT'] : null,
            dbName: $env['BLOCKCHAIN_DB_NAME'] ?? null,
            dbUser: $env['BLOCKCHAIN_DB_USER'] ?? null,
            dbPassword: $env['BLOCKCHAIN_DB_PASSWORD'] ?? null,
            participants: !empty($participants) ? array_values($participants) : null,
            nodeId: $env['BLOCKCHAIN_NODE_ID'] ?? null,
            rabbitmqHost: $env['BLOCKCHAIN_RABBITMQ_HOST'] ?? null,
            rabbitmqPort: isset($env['BLOCKCHAIN_RABBITMQ_PORT']) ? (int) $env['BLOCKCHAIN_RABBITMQ_PORT'] : null,
            rabbitmqUser: $env['BLOCKCHAIN_RABBITMQ_USER'] ?? null,
            rabbitmqPassword: $env['BLOCKCHAIN_RABBITMQ_PASSWORD'] ?? null,
            rabbitmqExchange: $env['BLOCKCHAIN_RABBITMQ_EXCHANGE'] ?? null,
            rabbitmqVhost: $env['BLOCKCHAIN_RABBITMQ_VHOST'] ?? '/',
            blockSize: (int) ($env['BLOCKCHAIN_BLOCK_SIZE'] ?? 100),
            blockTimeLimit: (int) ($env['BLOCKCHAIN_BLOCK_TIME_LIMIT'] ?? 10),
            mempoolMaxSize: (int) ($env['BLOCKCHAIN_MEMPOOL_MAX_SIZE'] ?? 10000),
            proposerInterval: (int) ($env['BLOCKCHAIN_PROPOSER_INTERVAL'] ?? 5),
            consensusTimeout: (int) ($env['BLOCKCHAIN_CONSENSUS_TIMEOUT'] ?? 30),
        );
    }

    /**
     * Check if distributed mode is enabled
     */
    public function isDistributed(): bool
    {
        return $this->enabled 
            && is_array($this->participants) 
            && count($this->participants) > 1;
    }

    /**
     * Check if blockchain database is configured
     */
    public function hasBlockchainDb(): bool
    {
        return $this->dbHost !== null 
            && $this->dbName !== null 
            && $this->dbUser !== null;
    }

    /**
     * Check if RabbitMQ is configured
     */
    public function hasRabbitMQ(): bool
    {
        return $this->rabbitmqHost !== null 
            && $this->rabbitmqExchange !== null;
    }

    /**
     * Load environment variables
     */
    private static function loadEnv(): array
    {
        $env = [];
        
        // Load .env file
        $envFile = self::getProjectRoot() . '/.env';
        if (file_exists($envFile)) {
            $env = array_merge($env, self::parseEnvFile($envFile));
        }
        
        // Load .env.local if exists (overrides .env)
        $envLocalFile = self::getProjectRoot() . '/.env.local';
        if (file_exists($envLocalFile)) {
            $env = array_merge($env, self::parseEnvFile($envLocalFile));
        }
        
        return $env;
    }

    private static function parseEnvFile(string $file): array
    {
        $env = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }
            
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (($value[0] ?? '') === '"' && ($value[-1] ?? '') === '"') {
                    $value = substr($value, 1, -1);
                }
                
                $env[$key] = $value;
            }
        }
        
        return $env;
    }

    private static function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }
        return dirname(__DIR__, 6);
    }
}

