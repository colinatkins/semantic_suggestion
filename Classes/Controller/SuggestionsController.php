<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TalanHdf\SemanticSuggestion\Service\SuggestionService;
use TalanHdf\SemanticSuggestion\Service\UtilityService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Frontend controller for displaying semantic suggestions
 */
class SuggestionsController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_PAGE = 10;

    // Keep necessary properties
    protected PageAnalysisService $pageAnalysisService;
    protected SuggestionService $suggestionService;
    protected UtilityService $utility;

    // Keep the constructor
    public function __construct(
        PageAnalysisService $pageAnalysisService,
        SuggestionService $suggestionService,
        UtilityService $utility
    ) {
        $this->pageAnalysisService = $pageAnalysisService;
        $this->suggestionService = $suggestionService;
        $this->utility = $utility;
        // Logger injection is done via LoggerAwareTrait/Interface
        // $this->setLogger(...) // Is called automatically if configured in Services.yaml
    }

    /**
     * Display list of semantic suggestions for the current page
     */
    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        $this->utility->logDebug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        // TYPO3 14 compatible: Get page ID from request routing attribute
        $currentPageId = $this->getCurrentPageId();
        if ($currentPageId === 0) {
            $this->utility->logError('Could not determine current page ID in listAction');
            return $this->htmlResponse('Error: Could not determine current page ID.');
        }

        $currentLanguageUid = $this->utility->getCurrentLanguageUid(); // Ensure UtilityService works

        // Detailed log for debug
        $this->utility->logDebug('SuggestionsController - Getting suggestions', [
            'currentPageId' => $currentPageId,
            'currentLanguageUid' => $currentLanguageUid,
            'currentPage' => $currentPage,
            'itemsPerPage' => $itemsPerPage
        ]);
        
        $viewData = $this->suggestionService->generateSuggestionsFromDatabase($currentPageId, $currentPage, $itemsPerPage);

        // Log the returned data
        $this->utility->logDebug('SuggestionsController - View data prepared', [
            'suggestionsCount' => count($viewData['suggestions'] ?? []),
            'currentPageTitle' => $viewData['currentPageTitle'] ?? 'N/A',
            'pagination' => $viewData['pagination'] ?? 'N/A'
        ]);

        // Add debug logs to view data if necessary
        $viewData['debugLogs'] = $this->utility->getDebugLogs();

        $this->view->assignMultiple($viewData);
        return $this->htmlResponse();
    }

    /**
     * Get current page ID from request (TYPO3 14 compatible)
     * Falls back to $GLOBALS['TSFE'] for backward compatibility
     */
    private function getCurrentPageId(): int
    {
        // TYPO3 13+/14: Use request routing attribute
        $routing = $this->request->getAttribute('routing');
        if ($routing !== null && method_exists($routing, 'getPageId')) {
            return (int)$routing->getPageId();
        }

        // Fallback for older TYPO3 versions or edge cases
        if (isset($GLOBALS['TSFE']) && isset($GLOBALS['TSFE']->id)) {
            return (int)$GLOBALS['TSFE']->id;
        }

        return 0;
    }
}