<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $shopId = (int) Context::getContext()->shop->id;
    $groups = Db::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'group');

    foreach ($groups as $g) {
        $idGroup = (int) $g['id_group'];
        $exists = (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'module_group '
            . 'WHERE id_module = ' . (int) $module->id
            . ' AND id_shop = ' . $shopId
            . ' AND id_group = ' . $idGroup
        );
        if (!$exists) {
            Db::getInstance()->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'module_group (id_module, id_shop, id_group) '
                . 'VALUES (' . (int) $module->id . ', ' . $shopId . ', ' . $idGroup . ')'
            );
        }
    }

    return true;
}
