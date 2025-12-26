<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi;

use Syntexa\Core\Attributes\AsModule;

/**
 * Configuration for the UserApi Module (Office CRM/ERP Validator Node)
 */
#[AsModule(
    name: 'user-api',
    active: true,
    role: 'validator' 
)]
class ModuleConfig
{
}
