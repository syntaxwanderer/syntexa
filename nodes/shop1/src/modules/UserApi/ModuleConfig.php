<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserApi Module (Shop 1 Validator Node)
 */
#[AsModule(
    name: 'user-api',
    active: true,
    role: 'validator' 
)]
class ModuleConfig
{
}
