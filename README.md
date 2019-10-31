# pcModuleActivator

OXID eShop 6 module activation script, eg. for deployments.

## Installation
1. get by composer `composer require proudcommerce/moduleactivator`
2. copy `vendor/proudcommerce/moduleactivator/moduleActivator.php` (eg. to `/source/bin` folder)
3. execute `php source/bin/moduleActivator.php`

## Features
- Module activation order
- Exclude modules from activation
- Exclude tplblocks from activation
- Sync settings to subshops (EE)
- Generate views
- Clear tmp

## Screenshot
![pcModuleActivator](https://raw.githubusercontent.com/proudcommerce/pcModuleActivator/master/pcModuleActivator_screenshot.png)