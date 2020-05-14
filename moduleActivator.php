<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @copyright   ProudCommerce | 2020
 * @link        www.proudcommerce.com
 * @package     pcModuleActivator
 * @version     2.1.3
 * @author      Tobias Merkl <https://github.com/tabsl>
 **/

if (PHP_SAPI != 'cli') {
    die("Only cli execution allowed!");
}
require __DIR__ . '../../../vendor/autoload.php';

use \ProudCommerce\ModuleActivator\pcModuleActivator;

try {
	// 3 = shopid (default if not set = 1)
    $moduleActivator = new pcModuleActivator(3);

    /*
    // Example configuration
    $moduleActivator->setGenerateViews(true);
    $moduleActivator->setExcludeModules(['moduleid1']);
    $moduleActivator->setModuleOrderFirst(['moduleid2', 'moduleid3']);
    $moduleActivator->setModuleOrderLast(['moduleid4']);
    $moduleActivator->setExcludeBlocks([
        ['oxmodule'    => 'trosofortueberweisung',
         'oxtemplate'  => 'page/checkout/payment.tpl',
         'oxblockname' => 'select_payment',
         'oxfile'      => 'trosofortueberweisung_paymentSelector.tpl'
        ]
    ]);
    */

    $moduleActivator->execute();
} catch (exception $e) {
    print_r($e);
}


