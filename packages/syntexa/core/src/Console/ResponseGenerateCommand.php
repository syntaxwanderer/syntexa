<?php

declare(strict_types=1);

namespace Syntexa\Core\Console;

use Syntexa\Core\CodeGen\ResponseWrapperGenerator;

class ResponseGenerateCommand
{
    public static function run(array $argv): int
    {
        array_shift($argv); // script name

        try {
            if (empty($argv) || $argv[0] === '--all') {
                if (!empty($argv) && $argv[0] === '--all') {
                    array_shift($argv);
                }
                ResponseWrapperGenerator::generateAll();
                return 0;
            }

            $identifier = $argv[0];
            ResponseWrapperGenerator::generate($identifier);
            echo "✨ Response wrapper generated for {$identifier}\n";
            return 0;
        } catch (\Throwable $e) {
            fwrite(STDERR, "❌ {$e->getMessage()}\n");
            return 2;
        }
    }
}

