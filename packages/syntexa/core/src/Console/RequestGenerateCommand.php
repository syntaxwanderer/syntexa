<?php

declare(strict_types=1);

namespace Syntexa\Core\Console;

use Syntexa\Core\CodeGen\RequestWrapperGenerator;

class RequestGenerateCommand
{
    public static function run(array $argv): int
    {
        array_shift($argv); // script name

        try {
            if (empty($argv) || $argv[0] === '--all') {
                if (!empty($argv) && $argv[0] === '--all') {
                    array_shift($argv);
                }
                RequestWrapperGenerator::generateAll();
                return 0;
            }

            $identifier = $argv[0];
            RequestWrapperGenerator::generate($identifier);
            echo "✨ Request wrapper generated for {$identifier}\n";
            return 0;
        } catch (\Throwable $e) {
            fwrite(STDERR, "❌ {$e->getMessage()}\n");
            return 2;
        }
    }

    private static function printUsage(): void
    {
        echo <<<TXT
Usage:
  bin/syntexa request:generate               Generate wrappers for all external requests
  bin/syntexa request:generate --all         Same as above (explicit)
  bin/syntexa request:generate <Request>     Generate/refresh a specific request

Examples:
  bin/syntexa request:generate
  bin/syntexa request:generate Syntexa\\User\\Application\\Request\\LoginApiRequest
  bin/syntexa request:generate LoginFormRequest

TXT;
    }
}

