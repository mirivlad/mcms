<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;
use MCMS\Core\Events\CoreEvents;

// Shield emits the registered User entity as the only argument.
Events::on('register', static function ($user): void {
    Events::trigger(CoreEvents::USER_CREATED, $user);
});

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (! is_cli() && ENVIRONMENT !== 'testing') {
        $runtime = service('mcmsModuleRuntime');
        if (is_file(WRITEPATH . 'backups/restore-active.json')) {
            return;
        }
        if ($runtime->maintenance() !== null) {
            service('mcmsModuleUpdates')->finalize();
        }
        if ($runtime->maintenance() === null) {
            $runtime->readLock();
            service('mcmsThemeUpdates')->recover();
        }
        // PHP closes the lock resource after shutdown callbacks. Releasing in
        // an early shutdown callback would let migrations overlap later writes.
    }

    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});
