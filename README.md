Daemon Bundle
=============

This is a daemonization library to use in Symfony2 as a Bundle

[![Build Status](https://secure.travis-ci.org/s-edy/SinyDaemonBundle.png)](http://travis-ci.org/s-edy/SinyDaemonBundle)

Installation
-------------

### 1) Add the following lines in your deps file

```
[SinyDaemonBundle]
    git=git://github.com/s-edy/SinyDaemonBundle.git
    target=bundles/Siny/DaemonBundle
```

### 2) Run venders scpript

```
$ php bin/venders install
```

### 3) Add the Siny namespace to your autoloader

```php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
	// ...
	'Siny'             => __DIR__.'/../vendor/bundles',
));
```

### 4) Add this bundle to your application's kernel

```php
<?php
// app/AppKernel.php

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
        	// ...
            new Siny\DaemonBundle\SinyDaemonBundle(),
        );
```

