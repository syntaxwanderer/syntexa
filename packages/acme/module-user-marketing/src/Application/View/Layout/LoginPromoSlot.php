<?php

declare(strict_types=1);

namespace Acme\Marketing\Application\View\Layout;

use Syntexa\Frontend\Attributes\AsLayoutSlot;

#[AsLayoutSlot(
    handle: 'auth.login',
    slot: 'auth.login.sidebar',
    template: '@user-marketing/block/login/promo-slot.html.twig',
    context: [
        'title' => 'Members Perks',
        'message' => 'Join the loyalty club and unlock seasonal rewards.'
    ],
    priority: 50
)]
class LoginPromoSlot
{
}

