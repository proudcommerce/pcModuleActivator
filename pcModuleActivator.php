<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @copyright   ProudCommerce | 2019
 * @link        www.proudcommerce.com
 * @package     pcModuleActivator
 * @version     1.0.0
 * @author      Tobias Merkl <https://github.com/tabsl>
 * @author      Florian Engelhardt <https://github.com/flow-control>
 **/

declare(strict_types=1);
require_once dirname(__FILE__) . "/../bootstrap.php";

use \OxidEsales\Eshop\Core\DatabaseProvider;
use \OxidEsales\Eshop\Core\Registry;

/**
 * Class pcModuleActivator
 */
class pcModuleActivator
{

    /**
     * Gernate views after module actication
     *
     * @var bool
     */
    protected $generateViews = false;

    /**
     * Exclude modules from activation (module id)
     *
     * @var array
     */
    protected $excludeModules = [/*'moduleid'*/];

    /**
     * Modules which would be activated at first (module id)
     *
     * @var array
     */
    protected $moduleOrderFirst = [/*'moduleid'*/];

    /**
     * Modules which would be activated at last (module id)
     *
     * @var array
     */
    protected $moduleOrderLast = [/*'moduleid'*/];

    /*
     * Exclude template blocks (deactivate after module installation)
     *
     * @var array
     */
    protected $excludeBlocks = [
        /*['oxmodule'    => 'trosofortueberweisung',
         'oxtemplate'  => 'page/checkout/payment.tpl',
         'oxblockname' => 'select_payment',
         'oxfile'      => 'trosofortueberweisung_paymentSelector.tpl'
        ]*/
    ];

    /**
     * Execute module ativation
     */
    public function execute()
    {
        $this->clearModules();
        $this->activateModules();
        if ('EE' === \OxidEsales\Facts\Facts::getEdition()) {
            $this->syncModulesToSubShops();
            $this->syncTplBlocksToSubShops();
        }
        $this->deactivateBlocks();
        $this->clearTmp();
        if ($this->generateViews === true) {
            $this->generateViews();
        }
    }

    /**
     * Delete module settings from database
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function clearModules()
    {
        echo 'clearing modules ... ';
        $oDb = DatabaseProvider::getDb();
        $oDb->Execute(
            "DELETE
            FROM oxconfig
            WHERE oxvarname LIKE '%module%'
        "
        );
        echo "\033[0;32mDONE\033[0m\n";
    }


    /**
     * Activate modules
     */
    protected function activateModules()
    {
        try {
            echo 'activating modules ... ', "\n";
            // get module list
            $moduleList = oxNew(\OxidEsales\Eshop\Core\Module\ModuleList::class);
            // reset base::config
            $moduleList->setConfig(null);
            $aModules = array_keys(
                $moduleList->getModulesFromDir(
                    Registry::getConfig()->getModulesDir()
                )
            );
            $aModules = $this->prepareActivationOrder($aModules);
            $moduleInstaller = oxNew(\OxidEsales\Eshop\Core\Module\ModuleInstaller::class);
            foreach ($aModules as $sModule) {
                echo '  -> activating module "', $sModule, '" ... ';
                if (!in_array($sModule, $this->excludeModules)) {
                    $module = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);
                    if ($module->load($sModule)) {
                        $moduleInstaller->activate($module);
                        echo "\033[0;32mDONE\033[0m\n";
                    } else {
                        echo "\033[1;33mFAILED\033[0m\n";
                    }
                } else {
                    echo "\033[1;31mDISABLED\033[0m\n";
                }
            }
            echo 'activating modules ... ', "\033[0;32mDONE\033[0m\n";
        } catch (\Throwable $e) {
            print_r($e);
        }
    }

    /**
     * Prepare activation order for modules
     *
     * @param $aModules
     *
     * @return array
     */
    protected function prepareActivationOrder($aModules)
    {
        $toRemove = array_merge($this->moduleOrderFirst, $this->moduleOrderLast);
        $diffList = array_diff($aModules, $toRemove);
        sort($diffList);

        return array_merge($this->moduleOrderFirst, $diffList, $this->moduleOrderLast);
    }

    /**
     * Sync modules to subshops
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function syncModulesToSubShops()
    {
        echo 'syncing modules to subshops ... ';
        DatabaseProvider::getDb()->startTransaction();
        DatabaseProvider::getDb()->execute(
            "
            DELETE FROM oxconfig
            WHERE oxvarname LIKE '%module%'
              AND oxshopid != 1
        "
        );
        $iRet = DatabaseProvider::getDb()->execute(
            "
            INSERT INTO oxconfig
            SELECT MD5(UUID()), oxshops.oxid, oxconfig.oxmodule, oxconfig.oxvarname,
                   oxconfig.oxvartype, oxconfig.oxvarvalue, oxconfig.oxtimestamp
            FROM oxconfig
            LEFT JOIN oxshops ON oxshops.oxid != 1
            WHERE oxvarname LIKE '%module%'
              AND oxshopid = 1
        "
        );
        if ($iRet) {
            DatabaseProvider::getDb()->commitTransaction();
            echo "\033[0;32mDONE\033[0m\n";
        } else {
            DatabaseProvider::getDb()->rollbackTransaction();
            echo "\033[0;31mFAILED\033[0m\n";
        }
    }


    /**
     * Sync blocks to subshops
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function syncTplBlocksToSubShops()
    {
        echo 'syncing template blocks to subshops ... ';
        DatabaseProvider::getDb()->startTransaction();
        DatabaseProvider::getDb()->execute(
            "
            DELETE FROM oxtplblocks
            WHERE oxshopid != 1;
        "
        );
        $iRet = DatabaseProvider::getDb()->execute(
            "
            INSERT INTO oxtplblocks
            SELECT MD5(UUID()), 1, oxshops.oxid, oxtplblocks.oxtheme, oxtplblocks.oxtemplate,
                   oxtplblocks.oxblockname, oxtplblocks.oxpos, oxtplblocks.oxfile,
                   oxtplblocks.oxmodule, oxtplblocks.oxtimestamp
            FROM oxtplblocks
            LEFT JOIN oxshops ON oxshops.oxid != 1
            WHERE oxshopid = 1
        "
        );
        if ($iRet) {
            DatabaseProvider::getDb()->commitTransaction();
            echo "\033[0;32mDONE\033[0m\n";
        } else {
            DatabaseProvider::getDb()->rollbackTransaction();
            echo "\033[0;31mFAILED\033[0m\n";
        }
    }

    /**
     * Deactivate blocks
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function deactivateBlocks()
    {
        echo "deactivating not needed template blocks ...\n";
        if (!empty($this->excludeBlocks)) {
            foreach ($this->excludeBlocks as $excludeBlock) {
                $sql = 'UPDATE oxtplblocks SET oxactive = 0 WHERE ';
                foreach ($excludeBlock as $key => $value) {
                    $sql .= $key . ' = "' . $value . '" AND ';
                }
                echo '  -> deactivate block "' . implode($excludeBlock, '|') . '" ';
                $sql = substr($sql, 0, -4);
                DatabaseProvider::getDb()->startTransaction();
                $iRet = DatabaseProvider::getDb()->execute($sql);
                if ($iRet) {
                    DatabaseProvider::getDb()->commitTransaction();
                    echo "\033[0;32mDONE\033[0m\n";
                } else {
                    DatabaseProvider::getDb()->rollbackTransaction();
                    echo "\033[0;31mFAILED\033[0m\n";
                }
            }
        }
    }

    /**
     * Generate database views
     */
    private function generateViews(): void
    {

        echo 'generating views ... ';
        try {
            require_once(VENDOR_PATH . 'oxid-esales/oxideshop-db-views-generator/generate_views.php');
        } catch (\Throwable $e) {
            print_r($e);
        }
        echo "\033[0;32mDONE\033[0m" . PHP_EOL;
    }

    /**
     * Clear tmp directory
     */
    protected function clearTmp()
    {
        echo 'clearing tmp ... ';
        $tmpDir = realpath(dirname(__FILE__) . '/../tmp/');
        $files = glob($tmpDir . '/*.txt');
        $files += glob($tmpDir . '/*.php');
        array_map('unlink', $files);
        echo "\033[0;32mDONE\033[0m\n";
    }

}

$moduleSync = new pcModuleActivator();
$moduleSync->execute();
