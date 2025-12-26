<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserApi Module (Blockchain Server Validator Node)
 */
#[AsModule(
    name: 'user-api',
    active: true,
    role: 'validator'
)]
class ModuleConfig
{
}
