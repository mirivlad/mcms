<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\Auth as ShieldAuth;

class Auth extends ShieldAuth
{
    public array $views = [
        'login'                       => 'auth/login',
        'register'                    => 'auth/register',
        'layout'                      => 'auth/layout',
        'action_email_2fa'            => '\\CodeIgniter\\Shield\\Views\\email_2fa_show',
        'action_email_2fa_verify'     => '\\CodeIgniter\\Shield\\Views\\email_2fa_verify',
        'action_email_2fa_email'      => '\\CodeIgniter\\Shield\\Views\\Email\\email_2fa_email',
        'action_email_activate_show'  => '\\CodeIgniter\\Shield\\Views\\email_activate_show',
        'action_email_activate_email' => '\\CodeIgniter\\Shield\\Views\\Email\\email_activate_email',
        'magic-link-login'            => '\\CodeIgniter\\Shield\\Views\\magic_link_form',
        'magic-link-message'          => '\\CodeIgniter\\Shield\\Views\\magic_link_message',
        'magic-link-email'            => '\\CodeIgniter\\Shield\\Views\\Email\\magic_link_email',
    ];

    public array $redirects = [
        'register'          => '/account',
        'login'             => '/account',
        'logout'            => 'login',
        'force_reset'       => '/',
        'permission_denied' => '/',
        'group_denied'      => '/',
    ];
}
