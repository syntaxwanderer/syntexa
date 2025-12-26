<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserFrontend;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserFrontend Module (Office Node Overlay)
 */
#[AsModule(
    name: 'UserFrontend',
    active: true,
    role: 'validator'
)]
class ModuleConfig
{
}
