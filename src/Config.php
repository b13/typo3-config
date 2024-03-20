<?php

declare(strict_types=1);

/*
 * This file is part of the package "typo3-config" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13;

use TYPO3\CMS\Core\Cache\Backend\RedisBackend;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;

/**
 * Class to use in your configuration files of a TYPO3 project.
 */
class Config
{
    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var Typo3Version
     */
    protected $version;
    /**
     * @var string
     */
    protected $configPath;
    /**
     * @var string
     */
    protected $varPath;
    /**
     * @var bool
     */
    protected $ddevEnvironment = false;
    /**
     * @var Config
     */
    protected static $instance;

    private function __construct()
    {
        $this->context = Environment::getContext();
        $this->version = new Typo3Version();
        $this->configPath = Environment::getConfigPath();
        $this->varPath = Environment::getVarPath();
        $this->ddevEnvironment = getenv('IS_DDEV_PROJECT') == 'true';
    }

    /**
     * @param bool $applyDefaults
     * @return static
     */
    public static function initialize(bool $applyDefaults = true): self
    {
        // Late static binding
        self::$instance = new static();
        if ($applyDefaults === false) {
            return self::$instance;
        }
        return self::$instance
            // use sensible default based on Context
            ->applyDefaults();
    }

    /**
     * @return static
     */
    public static function get(): self
    {
        return self::$instance;
    }

    public function applyDefaults(): self
    {
        // Include presets by default
        self::$instance
            ->forbidInvalidCacheHashQueryParameter()
            ->forbidNoCacheQueryParameter();

        if (self::$instance->context->isDevelopment() || self::$instance->context->isTesting()) {
            self::$instance->useDevelopmentPreset();
            if (self::$instance->ddevEnvironment) {
                self::$instance->useDDEVConfiguration();
            }
        } elseif (self::$instance->context->isProduction()) {
            self::$instance->useProductionPreset();
        }
        return $this;
    }

    /**
     * Include additional configurations by TYPO3_CONTEXT server variable
     *
     * Example:
     * - TYPO3_CONTEXT: Production/Qa
     * - Possible configuration files:
     *   1. config/production.php
     *   2. config/production/qa.php (higher priority)
     *
     * Allowed base TYPO3_CONTEXT values:
     * - Development
     * - Testing
     * - Production
     */
    public function includeContextDependentConfigurationFiles(): self
    {
        $orderedListOfContextNames = [];
        $currentContext = $this->context;
        do {
            $orderedListOfContextNames[] = (string)$currentContext;
        } while (($currentContext = $currentContext->getParent()));
        $orderedListOfContextNames = array_reverse($orderedListOfContextNames);
        foreach ($orderedListOfContextNames as $contextName) {
            $contextConfigFilePath = $this->configPath . '/system/' . strtolower($contextName) . '.php';
            if (file_exists($contextConfigFilePath)) {
                require($contextConfigFilePath);
            }
        }
        return $this;
    }

    /**
     * Append TYPO3_CONTEXT to site name in the TYPO3 backend
     */
    public function appendContextToSiteName(): self
    {
        if ($this->context->isProduction() === false) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] .= ' - ' . (string)$this->context;
        }
        return $this;
    }

    public function initializeDatabaseConnection(array $options = null, $connectionName = 'Default'): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName] = array_replace_recursive(
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName],
            $options
        );
        return $this;
    }

    /**
     * Default settings for production, can be overridden again in each project / production.php
     * @return $this
     */
    public function useProductionPreset(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = false;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = false;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = -1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['belogErrorReporting'] = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
        $this->disableDeprecationLogging();
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] = array_replace_recursive(
            [
                LogLevel::DEBUG => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::INFO => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::WARNING => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::ERROR => [
                    FileWriter::class => ['disabled' => false],
                ],
            ],
            $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration']
        );
        return $this;
    }

    public function useDevelopmentPreset(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['belogErrorReporting'] = E_ALL;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = E_ALL;
        $this->enableDeprecationLogging();
        // Log warnings to files
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::WARNING] = [
            FileWriter::class => ['disabled' => false],
        ];
        return $this;
    }

    public function useDDEVConfiguration(string $dbHost = null): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*.*';
        $this
            ->initializeDatabaseConnection(
                [
                    'dbname' => 'db',
                    'host' => $dbHost ?? ('ddev-' . getenv('DDEV_PROJECT') . '-db'),
                    'password' => 'db',
                    'port' => '3306',
                    'user' => 'db',
                ]
            )
            ->useImageMagick();

        $mailhogSmtpBindAddr = getenv('MH_SMTP_BIND_ADDR');
        if (is_string($mailhogSmtpBindAddr) && $mailhogSmtpBindAddr !== '') {
            $this->useMailpit($mailhogSmtpBindAddr);
        }

        return $this;
    }

    public function useImageMagick(string $path = '/usr/bin/'): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'ImageMagick';
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = $path;
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path_lzw'] = $path;
        return $this;
    }

    public function useGraphicsMagick(string $path = '/usr/bin/'): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'GraphicsMagick';
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = $path;
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path_lzw'] = $path;
        return $this;
    }

    public function useMailpit(string $host = 'localhost', int $port = null): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'smtp';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_encrypt'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_password'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_server'] = $host . ($port ? ':' . (string)$port : '');
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_username'] = '';
        return $this;
    }

    public function useMailhog(string $host = 'localhost', int $port = null): self
    {
        return $this->useMailpit($host, $port);
    }

    public function allowNoCacheQueryParameter(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['disableNoCacheParameter'] = false;
        return $this;
    }

    public function forbidNoCacheQueryParameter(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['disableNoCacheParameter'] = true;
        return $this;
    }

    public function allowInvalidCacheHashQueryParameter(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFoundOnCHashError'] = false;
        return $this;
    }

    public function forbidInvalidCacheHashQueryParameter(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFoundOnCHashError'] = true;
        return $this;
    }

    public function excludeQueryParameterForCacheHashCalculation(string $queryParameter): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = $queryParameter;
        return $this;
    }

    public function excludeQueryParametersForCacheHashCalculation(array $queryParameters): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
            $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'],
            $queryParameters
        );
        return $this;
    }

    public function enableDeprecationLogging(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration'][LogLevel::NOTICE]['TYPO3\CMS\Core\Log\Writer\FileWriter']['disabled'] = false;
        return $this;
    }

    public function disableDeprecationLogging(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration'][LogLevel::NOTICE]['TYPO3\CMS\Core\Log\Writer\FileWriter']['disabled'] = true;
        return $this;
    }

    /**
     * Additional Project-specific methods
     */
    public function configureExceptionHandlers(string $productionExceptionHandlerClassName, string $debugExceptionHandlerClassName): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = $productionExceptionHandlerClassName;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = $debugExceptionHandlerClassName;
        return $this;
    }

    /**
     * Configures a log file for solr based on the TYPO3 Context, with a separate file for solr.
     *
     * @param string $fileName
     * @param string|null $forceLogLevel
     * @return $this
     */
    public function autoconfigureSolrLogging(string $fileName = 'solr.log', string $forceLogLevel = null): self
    {
        if ($forceLogLevel !== null) {
            $logLevel = $forceLogLevel;
        } else {
            $logLevel = $this->context->isProduction() ? LogLevel::ERROR : LogLevel::DEBUG;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'] = [
            $logLevel => [
                FileWriter::class => [
                    'logFile' => $this->varPath . '/log/' . $fileName,
                ],
            ],
        ];
        return $this;
    }

    /**
     * Activates caching for Redis, if used in its environment.
     *
     * @param array|null $caches an associative array of [cache_name => default lifetime], if null, then we rely on best practices
     * @param string $redisHost alternative redis host
     * @param int $redisStartDb the start DB for the redis caches
     * @param int $redisPort alternative port for redis, usually 6379
     * @param null $alternativeCacheBackend alternative cache backend, useful if you use b13/graceful-caches
     * @return $this
     */
    public function initializeRedisCaching(array $caches = null, string $redisHost = '127.0.0.1', int $redisStartDb = 0, int $redisPort = 6379, $alternativeCacheBackend = null): self
    {
        $isVersion9 = $this->version->getMajorVersion() === 9;
        $isVersion12OrHigher = $this->version->getMajorVersion() >= 12;
        $cacheBackend = $alternativeCacheBackend ?? RedisBackend::class;
        $redisDb = $redisStartDb;
        $caches = $caches ?? [
                ($isVersion9 ? 'cache_pages' : 'pages') => 86400*30,
                ($isVersion9 ? 'cache_pagesection' : 'pagesection') => 86400*30,
                ($isVersion9 ? 'cache_hash' : 'hash') => 86400*30,
                ($isVersion9 ? 'cache_rootline' : 'rootline') => 86400*30,
                ($isVersion9 ? 'cache_extbase' : 'extbase') => 0,
        ];
        if ($isVersion12OrHigher) {
            unset($caches['pagesection'], $caches['cache_pagesection']);
        }
        foreach ($caches as $key => $lifetime) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$key]['backend'] = $cacheBackend;
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$key]['options'] = [
                'database' => $redisDb++,
                'hostname' => $redisHost,
                'port' => $redisPort,
                'defaultLifetime' => $lifetime,
            ];
        }
        return $this;
    }

    /**
     * Useful for distributed systems to put caches outside of an NFS mount.
     *
     * @param string $path
     * @param array|null $applyForCaches
     * @return $this
     */
    public function setAlternativeCachePath(string $path, array $applyForCaches = null): self
    {
        $applyForCaches = $applyForCaches ?? [
                'cache_core',
                'fluid_template',
                'assets',
                'l10n',
            ];
        foreach ($applyForCaches as $cacheName) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheName]['options']['cacheDirectory'] = $path;
        }
        return $this;
    }
}
