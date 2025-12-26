<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserApi Module (Blog Observer Node)
 */
#[AsModule(
    name: 'user-api',
    active: true,
    role: 'observer' // This node acts as an observer (listener)
)]
class ModuleConfig
{
}
