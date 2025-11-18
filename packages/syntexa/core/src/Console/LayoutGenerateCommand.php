<?php

declare(strict_types=1);

namespace Syntexa\Core\Console;

use Syntexa\Core\CodeGen\LayoutGenerator;

class LayoutGenerateCommand
{
    public static function run(array $argv): int
    {
        array_shift($argv); // script name

        try {
            if (empty($argv) || $argv[0] === '--all') {
                if (!empty($argv) && $argv[0] === '--all') {
                    array_shift($argv);
                }
                LayoutGenerator::generateAll();
                return 0;
            }

            $identifier = $argv[0];
            LayoutGenerator::generate($identifier);
            echo "✨ Layout copied for {$identifier}\n";
            return 0;
        } catch (\Throwable $e) {
            fwrite(STDERR, "❌ {$e->getMessage()}\n");
            self::printUsage();
            return 2;
        }
    }

    private static function printUsage(): void
    {
        echo <<<TXT
Usage:
  bin/syntexa layout:generate             Copy all module layouts into src/ (activates everything)
  bin/syntexa layout:generate --all       Explicit alias for the same behaviour
  bin/syntexa layout:generate <id>        Copy a single layout (handle or Module/handle)

Examples:
  bin/syntexa layout:generate
  bin/syntexa layout:generate login
  bin/syntexa layout:generate UserFrontend/login

TXT;
    }
}



