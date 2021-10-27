<?php

/**
 * This example shows how project-specific configuration can be used and encapsulated as well.
 */
class ProjectConfig extends \B13\Config
{
    public function enableSolrLogging(string $fileName = 'solr.log'): self
    {
        if ($this->context->isProduction()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::ERROR;
        } elseif ($this->context->isDevelopment()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::DEBUG;
        } else {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::INFO;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'] = [
            $logLevel => [
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    'logFile' => $this->varPath . '/log/' . $fileName
                ]
            ],
        ];
        return $this;
    }
}

\B13\Config::initialize()
    ->enableSolrLogging()
    ->includeContextDependentConfigurationFiles();
