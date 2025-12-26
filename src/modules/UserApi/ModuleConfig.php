<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserApi Module
 */
#[AsModule(
    name: 'user-api',
    active: true,
    role: 'validator' // This node acts as a validator for User events
)]
class ModuleConfig
{
}
