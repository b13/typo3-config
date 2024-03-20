<?php

/**
 * This example shows how project-specific configuration can be used and encapsulated as well.
 */
class ProjectConfig extends \B13\Config
{
    public function configureCustomLoggingForSomething(string $fileName = 'something.log'): self
    {
        // do your own logic
        if ($this->context->isProduction()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::ERROR;
        } elseif ($this->context->isDevelopment()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::DEBUG;
        } else {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::INFO;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['MyVendor']['MyPath']['writerConfiguration'] = [
            $logLevel => [
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    'logFile' => $this->varPath . '/log/' . $fileName,
                ],
            ],
        ];
        return $this;
    }
}

\B13\Config::initialize()
    ->configureCustomLoggingForSomething()
    ->enableSolrLogging()
    ->includeContextDependentConfigurationFiles();
