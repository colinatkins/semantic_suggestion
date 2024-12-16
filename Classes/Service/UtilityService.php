<?php

namespace TalanHdf\SemanticSuggestion\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UtilityService
{
    protected array $debugLogs = [];


    public function __construct(protected PageAnalysisService $pageAnalysisService, protected LoggerInterface $logger)
    {
    }

    public function getCurrentLanguageUid(): int
    {
        return GeneralUtility::makeInstance(Context::class)->getAspect('language')->getId();
    }

    public function logDebug(string $message, array $context = []): void
    {
        $debugMode = $this->pageAnalysisService->getSettings()['debugMode'] ?? false;
        if ($debugMode && $this->logger instanceof LoggerInterface) {
            $this->logger->debug($message, $context);
            $this->debugLogs[] = ['message' => $message, 'context' => $context];
        }
    }

    public function getDebugLogs(): array
    {
        return $this->debugLogs;
    }

}