# pcModuleActivator

OXID eShop 6.0 module (CE/PE/EE) activation script , eg. for deployments.

---

For OXID 6.1 (oxrun): https://github.com/OXIDprojects/oxrun#modulemultiactivate

For OXID 6.2 (oxid console): https://github.com/proudcommerce/oxid-console-moduleactivator

---

## Installation
1. get by composer `composer require proudcommerce/moduleactivator`
2. copy `vendor/proudcommerce/moduleactivator/moduleActivator.php` to `/source/bin/moduleActivator.php`
3. execute `php source/bin/moduleActivator.php`

## Features
- Module activation order
- Exclude modules from activation
- Exclude tplblocks from activation
- Generate views
- Clear tmp

## Screenshot
![pcModuleActivator](https://raw.githubusercontent.com/proudcommerce/pcModuleActivator/master/pcModuleActivator_screenshot.png)

## Notice
- Sometimes there are some activation problems in subshops (ee) :-(

## Credentials
Tobias Merkl <https://github.com/tabsl>
Florian Engelhardt <https://github.com/flow-control>
