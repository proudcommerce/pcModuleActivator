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

namespace ProudCommerce\ModuleActivator;

require __DIR__ . "/../../../source/bootstrap.php";

use \OxidEsales\Eshop\Core\DatabaseProvider;
use \OxidEsales\Eshop\Core\Registry;

/**
 * Class pcModuleActivator
 */
class pcModuleActivator
{

    /**
     * pcModuleActivator version
     *
     * @var string
     */
    protected $version = '2.1.3';

    /**
     * Shop id
     *
     * @var int\null
     */
    protected $shopId = null;

    /**
     * Generate views after module actication
     *
     * @var bool
     */
    protected $generateViews = false;

    /**
     * Exclude modules from activation (module id)
     *
     * @var array
     */
    protected $excludeModules = [];

    /**
     * Modules which would be activated at first (module id)
     *
     * @var array
     */
    protected $moduleOrderFirst = [];

    /**
     * Modules which would be activated at last (module id)
     *
     * @var array
     */
    protected $moduleOrderLast = [];

    /*
     * Exclude template blocks (deactivate after module installation)
     *
     * @var array
     */
    /**
     * @var array
     */
    protected $excludeBlocks = [];

    /**
     * pcModuleActivator constructor.
     * @param int|int $shopId
     */
    public function __construct(int $shopId = 1)
    {
        $this->shopId = $shopId;
    }

    /**
     * @return bool
     */
    public function getGenerateViews()
    {
        return $this->generateViews;
    }

    /**
     * @param bool $generateViews
     *
     * @return bool
     */
    public function setGenerateViews(bool $generateViews): bool
    {
        return $this->generateViews = $generateViews;
    }

    /**
     * @return array
     */
    public function getExcludeModules()
    {
        return $this->excludeModules;
    }

    /**
     * @param array $excludeModules
     *
     * @return array
     */
    public function setExcludeModules(array $excludeModules): array
    {
        return $this->excludeModules = $excludeModules;
    }

    /**
     * @return array
     */
    public function getModuleOrderFirst()
    {
        return $this->moduleOrderFirst;
    }

    /**
     * @param array $moduleOrderFirst
     *
     * @return array
     */
    public function setModuleOrderFirst(array $moduleOrderFirst): array
    {
        return $this->moduleOrderFirst = $moduleOrderFirst;
    }

    /**
     * @return array
     */
    public function getModuleOrderLast()
    {
        return $this->moduleOrderLast;
    }

    /**
     * @param array $moduleOrderLast
     *
     * @return array
     */
    public function setModuleOrderLast(array $moduleOrderLast): array
    {
        return $this->moduleOrderLast = $moduleOrderLast;
    }

    /**
     * @return array
     */
    public function getExcludeBlocks()
    {
        return $this->excludeBlocks;
    }

    /**
     * @param array $excludeBlocks
     *
     * @return array
     */
    public function setExcludeBlocks(array $excludeBlocks): array
    {
        return $this->excludeBlocks = $excludeBlocks;
    }

    /**
     * Execute module activation
     */
    public function execute()
    {
        echo 'pcModuleActivator ' . $this->version . "\n";
        $this->switchToShop();
        $this->clearModules();
        $this->activateModules();
        $this->deactivateBlocks();
        $this->clearTmp();
        if ($this->generateViews === true) {
            $this->generateViews();
        }
    }

    /**
     * credentials: https://github.com/OXIDprojects/oxrun/blob/master/src/Oxrun/Application.php#L229 thx ;-)
     *
     * @throws \Exception
     */
    public function switchToShop()
    {
        $shopId = $this->shopId;
        $keepThese = [\OxidEsales\Eshop\Core\ConfigFile::class];
        $registryKeys = \OxidEsales\Eshop\Core\Registry::getKeys();
        foreach ($registryKeys as $key) {
            if (in_array($key, $keepThese)) {
                continue;
            }
            \OxidEsales\Eshop\Core\Registry::set($key, null);
        }

        $utilsObject = new \OxidEsales\Eshop\Core\UtilsObject;
        $utilsObject->resetInstanceCache();
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\UtilsObject::class, $utilsObject);

        \OxidEsales\Eshop\Core\Module\ModuleVariablesLocator::resetModuleVariables();
        \OxidEsales\Eshop\Core\Registry::getSession()->setVariable('shp', $shopId);

        //ensure we get rid of all instances of config, even the one in Core\Base
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\Config::class, null);
        \OxidEsales\Eshop\Core\Registry::getConfig()->setConfig(null);
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\Config::class, null);

        $moduleVariablesCache = new \OxidEsales\Eshop\Core\FileCache();
        $shopIdCalculator = new \OxidEsales\Eshop\Core\ShopIdCalculator($moduleVariablesCache);

        if (($shopId != $shopIdCalculator->getShopId())
            || ($shopId != \OxidEsales\Eshop\Core\Registry::getConfig()->getShopId())
        ) {
            throw new \Exception('Failed to switch to subshop id ' . $shopId . " Calculate ID: " . $shopIdCalculator->getShopId() . " Config ShopId: " . \OxidEsales\Eshop\Core\Registry::getConfig()->getShopId());
        }

        echo 'set shop id ' . $shopId . " ... ";
        echo "\033[0;32mDONE\033[0m\n";
    }

    /**
     * Delete module settings from database
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function clearModules()
    {
        $varnames = ['aDisabledModules', 'aModuleControllers', 'aModuleEvents', 'aModuleExtensions', 'aModuleFiles', 'aModulePaths', 'aModules', 'aModuleTemplates', 'aModuleVersions'];
        $varname = implode($varnames, "', '");

        echo 'clearing modules ... ';
        $oDb = DatabaseProvider::getDb();
        $oDb->Execute(
            "DELETE
            FROM oxconfig
            WHERE oxshopid = " . $this->shopId . "
            AND oxvarname IN ('" . $varname . "')
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
            try {
                $aModules = array_keys(
                    $moduleList->getModulesFromDir(
                        Registry::getConfig()->getModulesDir()
                    )
                );
            } catch (\Exception $ex) {
                echo "\033[1;33mFAILED 1\033[0m\n" . $ex->getMessage() . "\n";
            }
            $aModules = $this->prepareActivationOrder($aModules);
            $moduleInstaller = oxNew(\OxidEsales\Eshop\Core\Module\ModuleInstaller::class);
            foreach ($aModules as $sModule) {
                echo '  -> activating module "', $sModule, '" ... ';
                if (!in_array($sModule, $this->excludeModules)) {
                    try {
                        $module = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);
                        if ($module->load($sModule)) {
                            if ($moduleInstaller->activate($module)) {
                                echo "\033[0;32mDONE\033[0m\n";
                            } else {
                                echo "\033[1;33mACTIVATION FAILED \033[0m\n";
                            }
                        } else {
                            echo "\033[1;33mLOADING FAILED\033[0m\n";
                        }
                    } catch (\Exception $ex) {
                        echo "\033[1;33mFAILED\033[0m\n" . $ex->getMessage() . "\n";
                    }
                } else {
                    echo "\033[1;31mDISABLED\033[0m\n";
                }
            }
            echo 'activating modules ... ', "\033[0;32mDONE\033[0m\n";
        } catch (\Throwable $e) {
            echo "\033[1;33mGENERAL ACTIVATION FAILED\033[0m\n" . $ex->getMessage() . "\n";
        }
    }

    /**
     * Prepare activation order for modules
     *
     * @param $aModules
     *
     * @return array
     */
    protected function prepareActivationOrder($aModules): array
    {
        $toRemove = array_merge($this->moduleOrderFirst, $this->moduleOrderLast);
        $diffList = array_diff($aModules, $toRemove);
        sort($diffList);

        return array_merge($this->moduleOrderFirst, $diffList, $this->moduleOrderLast);
    }


    /**
     * Deactivate blocks
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function deactivateBlocks()
    {
        echo "deactivating template blocks ... ";
        if (!empty($this->excludeBlocks)) {
            foreach ($this->excludeBlocks as $excludeBlock) {
                $sql = 'UPDATE oxtplblocks SET oxactive = 0 WHERE oxshopid = ' . $this->shopId . ' AND ';
                foreach ($excludeBlock as $key => $value) {
                    $sql .= $key . ' = "' . $value . '" AND ';
                }
                echo "\n" . '  -> deactivate block "' . implode($excludeBlock, ' | ') . '" ';
                $sql = substr($sql, 0, -4);
                DatabaseProvider::getDb()->startTransaction();
                $iRet = DatabaseProvider::getDb()->execute($sql);
                if ($iRet) {
                    DatabaseProvider::getDb()->commitTransaction();
                    echo "\033[0;32mDONE\033[0m";
                } else {
                    DatabaseProvider::getDb()->rollbackTransaction();
                    echo "\033[0;31mFAILED\033[0m";
                }
            }
        }
        echo "\ndeactivating template blocks ... \e[0;32mDONE\e[0m\n";
    }

    /**
     * Generate database views
     */
    private function generateViews()
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
