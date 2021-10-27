# Manage TYPO3 System-Wide Configuration

> TLDR: Don't repeat yourself.

At b13 we run similar code and site-specific functionality based on the
actual environment (development / production context). For this, we
usually have a list of default "best practice" settings which
we usually copy from one project to the next.

This small library ships with a single PHP class which
makes our life a bit easier when setting global `$TYPO3_CONF_VARS` settings,
which usually takes place in LocalConfiguration, AdditionalConfiguration
and the extensions' `ext_localconf.php` files.

### Reason 1: We want context-dependent config files

With our configuration class, TYPO3's "AdditionalConfiguration"
(located in :file:`typo3conf/AdditionalConfiguration.php`) just looks like this:

    <?php

        \B13\Config::initialize()
            ->includeContextDependentConfigurationFiles();

This sets some sensible defaults (see below), and also checks
for the existence of the following files:

```
config/development.php
config/production.php
```

If you have a TYPO3_CONTEXT with Subcontexts such as "Production/QA"
then the file `config/production/qa.php` is also included,
in addition to `config/production.php`.

### Reason 2: We don't want to repeat the same "best b13 practice"

The `initialize()` method sets sensible defaults for a specific environment,
also adds the actual TYPO3_CONTEXT to the Project Name (e.g. "My Site - Development"),
activates debugging for Development environments, and deactivates deprecation
logging in production by default.

b13 uses DDEV-Local for local environments, however, it is somehow
tedious to maintain the same configuration over and over again.  If DDEV-Local
is in use, `initialize` automatically sets the respective  settings for
DDEV-Local environments.

If you want to avoid any kind of magic, you can just use this in your AdditionalConfiguration file.

    <?php
        \B13\Config::initialize(false)
            ->includeContextDependentConfigurationFiles();

On top, the API ships with useful helper methods which we accustomed
to set specific values. You can use this API in combination with
setting other environment- or project-specific settings of course.

    \B13\Config::get()
        ->useMailhog()
        ->allowInvalidCacheHashQueryParameter()
        ->initializeRedisCaching();

    # Also set other TYPO3 configuration values as you need
    $GLOBALS['TYPO3_CONF_VARS']['FE']['versionNumberInFilename'] = 'embed';


## Installation

Install this package in `composer req b13/typo3-config` in your existing
TYPO3 project – you are ready to go. This package only supports TYPO3 v9 LTS
and higher, as we consider having multiple branches for multiple versions at
some point where this package provides a stable API while not modifying.

## Why we created this package

We use this approach for a few years now and publishing this package
helps us to maintain this logic in a standardized way. We invite
other web agencies to do the same, to see what everybody can improve
and we can collaborate.

TYPO3 does not have a good API to set such options
for developers, and this is our list of best practices, which
we love to share with the world until TYPO3 Core will provide
a standardized and better solution.

With TYPO3 it is possible to configure your system in many ways,
and some do this via extensions, and we do it like this, hence: a common
ground for us to use.

## Drawbacks

1. If you have a slow file system (NFS) or older PHP version, where
   your files are located, this package might slow down your file system
   due to a few more file look ups. Ideally we'd love to cache such
   information, but TYPO3 Core does not work with cached configuration
   for `TYPO3_CONF_VARS`.

2. Using the "Install Tool" to set global configuration values might
   not work as expected, as our configuration logic currently works
   AFTER `LocalConfiguration.php` has been loaded. This is one
   of the major issues with TYPO3 Core's configuration system.

## Naming TYPO3_CONTEXT

This package works best in TYPO3 projects if you use Git, Composer
and various environments. We usually use Contexts also in TYPO3's
site configuration and think it is very powerful to use if done
right. Internally we committed ourselves across projects to the following
names.

### Regular projects

* Development
* Development/DDEV
* Testing (CI)
* Testing/Unit (CI)
* Production/Live
* Production/Staging
* Production/Staging/Feature-TicketNo
* Production/Staging/Feature-TicketNo2

If you have one code base that powers multiple sites, we use sub-sub-schema
as well.

* Production/Staging/SiteA
* Production/Staging/SiteB
* Production/Staging/SiteC
* Production/Live/SiteA
* Production/Live/SiteB
* Production/Live/SiteC

## Thanks

Prior functionality was heavily inspired by [Neos Flow](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartII/Configuration.html),
and our initial TYPO3 solution was developed by [Achim Fritz](https://github.com/achimfritz).

## License

The package is licensed under GPL v2+, same as the TYPO3 Core. For details see the LICENSE file in this repository.

## Credits

This package was created by [Achim Fritz](https://github.com/achimfritz) and [Benni Mack](https://github.com/bmack) in 2021 for [b13 GmbH](https://b13.com).

[Find more TYPO3 packages we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of the way we work, we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
