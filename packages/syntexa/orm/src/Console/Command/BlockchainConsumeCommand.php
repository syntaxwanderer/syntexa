<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\Blockchain\BlockchainConfig;
use Syntexa\Orm\Blockchain\BlockchainStorage;
use Syntexa\Orm\Blockchain\BlockchainTransaction;

/**
 * BlockchainConsumeCommand
 *
 * Consumes blockchain transactions from RabbitMQ fanout exchange and
 * stores them into the blockchain database.
 */
class BlockchainConsumeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('blockchain:consume')
            ->setDescription('Consume blockchain transactions from RabbitMQ and store them in blockchain DB')
            ->addOption('queue', 'q', InputOption::VALUE_OPTIONAL, 'Queue name (defaults to blockchain.{NODE_ID})')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Consume only one message and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!class_exists(\AMQPConnection::class)) {
            $io->error('ext-amqp is required to run blockchain:consume');
            return Command::FAILURE;
        }

        $config = BlockchainConfig::fromEnv();
        if (!$config->enabled || !$config->hasRabbitMQ()) {
            $io->warning('Blockchain or RabbitMQ is not enabled/configured. Check BLOCKCHAIN_* env variables.');
            return Command::SUCCESS;
        }

        if (!$config->hasBlockchainDb()) {
            $io->error('Blockchain DB is not configured (BLOCKCHAIN_DB_* env variables are required).');
            return Command::FAILURE;
        }

        $storage = new BlockchainStorage($config);

        $queueName = $input->getOption('queue') ?? ('blockchain.' . ($config->nodeId ?? 'node'));
        $once = (bool) $input->getOption('once');

        $io->title('Blockchain Consumer');
        $io->text('Exchange: ' . ($config->rabbitmqExchange ?? 'syntexa_blockchain'));
        $io->text('Queue: ' . $queueName);
        $io->text('Node ID: ' . ($config->nodeId ?? 'node-unknown'));
        $io->newLine();

        $connection = new \AMQPConnection([
            'host' => $config->rabbitmqHost ?? 'localhost',
            'port' => $config->rabbitmqPort ?? 5672,
            'login' => $config->rabbitmqUser ?? 'guest',
            'password' => $config->rabbitmqPassword ?? 'guest',
            'vhost' => $config->rabbitmqVhost ?? '/',
        ]);
        $connection->connect();
        $channel = new \AMQPChannel($connection);

        // Declare fanout exchange
        $exchange = new \AMQPExchange($channel);
        $exchange->setName($config->rabbitmqExchange ?? 'syntexa_blockchain');
        $exchange->setType(\defined('AMQP_EX_TYPE_FANOUT') ? AMQP_EX_TYPE_FANOUT : 'fanout');
        $exchange->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $exchange->declareExchange();

        // Declare durable queue for this node and bind to exchange
        $queue = new \AMQPQueue($channel);
        $queue->setName($queueName);
        $queue->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $queue->declareQueue();
        $queue->bind($exchange->getName());

        $io->success('Waiting for blockchain transactions. Press Ctrl+C to stop.');

        $processed = 0;

        $consumeCallback = function (\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($storage, $io, &$processed, $once) {
            $body = $envelope->getBody();
            try {
                $tx = BlockchainTransaction::fromJson($body);
                $storage->appendTransaction($tx);
                $queue->ack($envelope->getDeliveryTag());
                $processed++;

                if ($io->isVerbose()) {
                    $io->writeln(sprintf(
                        'Stored transaction %s for %s#%d (%s)',
                        $tx->transactionId,
                        $tx->entityClass,
                        $tx->entityId,
                        $tx->operation
                    ));
                }

                if ($once) {
                    return false; // Stop consuming
                }
            } catch (\Throwable $e) {
                $io->error('Failed to process transaction: ' . $e->getMessage());
                $queue->nack($envelope->getDeliveryTag());
            }

            return true;
        };

        if ($once) {
            $queue->consume($consumeCallback);
        } else {
            while (true) {
                $queue->consume($consumeCallback);
            }
        }

        $io->success(sprintf('Processed %d transaction(s)', $processed));
        return Command::SUCCESS;
    }
}


