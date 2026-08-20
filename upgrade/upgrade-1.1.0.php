<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    // These hooks were added after the original 1.0.0 installation. Keep the
    // upgrade idempotent so an existing installation receives the same hooks
    // as a fresh install.
    foreach ([
        'displayHeader',
        'displayCustomerAccountForm',
        'actionValidateFromCustomer',
        'actionValidateSendRenewPasswordLink',
    ] as $hook) {
        if (!$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}
