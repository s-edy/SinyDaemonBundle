<?php
/**
* This file is a part of Siny\DaemonBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

$temporary = __DIR__ . '/../tmp';
if (! file_exists($temporary)) {
    mkdir($temporary);
}

$vendorDirectory = __DIR__ . '/../vendor';

if (! file_exists($vendorDirectory . '/.composer/autoload.php')) {
    exit("You must install the dependencies by composer.phar with composer.json.");
}

require_once $vendorDirectory . '/.composer/autoload.php';

spl_autoload_register(function($class) {
    if (strpos($class, 'Siny\\DaemonBundle\\') === 0) {
        $file = __DIR__ . '/../' . implode('/', array_slice(explode('\\', $class), 2)) . '.php';
        if (file_exists($file) === false) {
            return false;
        }
        require_once $file;
        return true;
    }
});
