<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Overrides\Layout;

use Syntexa\Frontend\Attributes\AsLayoutOverride;

/**
 * Layout override for the login page demonstrating all override capabilities:
 * 
 * 1. ADD: Adds a promotional block before the login form
 * 2. ADD: Adds a custom info block after the form
 * 3. MOVE: Moves the help block to appear after the custom info block
 * 
 * This demonstrates how project-specific customizations can be made
 * without modifying the original module XML files.
 */
#[AsLayoutOverride(
    handle: 'login',
    operations: [
        // 1. Add promotional block at the top of main container
        [
            'type' => 'add',
            'into' => 'main',
            'block' => [
                'name' => 'LoginPromoBlock',
                'template' => '@user-frontend/block/login/promo.html.twig',
                'args' => [
                    'title' => 'Welcome Back!',
                    'message' => 'Sign in to access your account',
                    'showFeatures' => true
                ]
            ],
            'before' => 'Syntexa\\UserFrontend\\Application\\View\\Block\\LoginFormBlock'
        ],
        
        // 2. Add custom info block after the form
        [
            'type' => 'add',
            'into' => 'main',
            'block' => [
                'name' => 'LoginInfoBlock',
                'template' => '@user-frontend/block/login/info.html.twig',
                'args' => [
                    'text' => 'New user? Create an account to get started.'
                ]
            ],
            'after' => 'Syntexa\\UserFrontend\\Application\\View\\Block\\LoginFormBlock'
        ],
        
        // 3. Add a named help block (since original doesn't have a name)
        // In a real scenario, you might want to remove the original unnamed help block
        // and add this named version instead for better control
        [
            'type' => 'add',
            'into' => 'main',
            'block' => [
                'name' => 'LoginHelpBlock',
                'template' => '@user-frontend/block/login/help.html.twig'
            ],
            'after' => 'LoginInfoBlock'
        ],
        
        // 4. Example: Remove a block by name (if it existed)
        // ['type' => 'remove', 'name' => 'SomeBlockToRemove'],
        
        // 5. Example: Move a block to a different container
        // ['type' => 'move', 'name' => 'SomeBlock', 'into' => 'sidebar'],
    ],
    priority: 100
)]
class LoginLayoutOverride
{
    // This class serves only as a container for the attribute.
    // The actual override logic is handled by the framework.
    // All operations defined in the attribute will be automatically
    // applied when the 'login' layout handle is loaded.
}

