<?php
namespace TalanHdf\SemanticSuggestion\Controller;

// --- Imports ---
use Psr\Http\Message\ResponseInterface;
use TalanHdf\SemanticSuggestion\Service\SuggestionService;
use TalanHdf\SemanticSuggestion\Service\UtilityService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService; // Ensure this is used or remove
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController; // Import ActionController

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

    // Keep listAction() and other business methods
    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        // Log via the injected logger (ensure injection works via Services.yaml and setLogger)
        $this->logger->debug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        // Using $GLOBALS['TSFE'] directly may be less reliable in v12/v13,
        // consider retrieving page ID via $this->request if possible,
        // but for now keep $GLOBALS['TSFE']->id
        $currentPageId = (int)($GLOBALS['TSFE']->id ?? 0);
        if ($currentPageId === 0) {
             $this->logger->error('Could not determine current page ID in listAction');
              // Handle error, perhaps return an empty response or message
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
}