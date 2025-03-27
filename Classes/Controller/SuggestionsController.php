<?php
namespace TalanHdf\SemanticSuggestion\Controller;

use Psr\Http\Message\ResponseInterface;
use TalanHdf\SemanticSuggestion\Service\SuggestionService;
use TalanHdf\SemanticSuggestion\Service\UtilityService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class SuggestionsController extends AbstractCompatibleController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_PAGE = 10;

    protected PageAnalysisService $pageAnalysisService;
    protected SuggestionService $suggestionService;
    protected UtilityService $utility;

    public function __construct(
        PageAnalysisService $pageAnalysisService,
        SuggestionService $suggestionService,
        UtilityService $utility
    ) {
        $this->pageAnalysisService = $pageAnalysisService;
        $this->suggestionService = $suggestionService;
        $this->utility = $utility;
        $this->setLogger(GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__));
        
        // Ne pas appeler initializeControllerProperties() ici
    }

/**
     * Initialize action
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        /*
        // Ajouter explicitement les chemins de templates
        if (method_exists($this->view, 'setTemplateRootPaths')) { // CETTE LIGNE CAUSE L'ERREUR car $this->view peut être null
            $this->view->setTemplateRootPaths([
                0 => 'EXT:semantic_suggestion/Resources/Private/Templates/'
            ]);
        }
        */

        // Rien d'autre à initialiser ici, le parent s'occupe des propriétés sensibles
    }

    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        $this->utility->logDebug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        $currentPageId = $GLOBALS['TSFE']->id;
        $currentLanguageUid = $this->utility->getCurrentLanguageUid();
        $viewData = $this->suggestionService->generateSuggestionsFromDatabase($currentPageId, $currentPage, $itemsPerPage);
        
        // Ajouter les logs de debug aux données de la vue si nécessaire
        $viewData['debugLogs'] = $this->utility->getDebugLogs();

        $this->view->assignMultiple($viewData);
        return $this->htmlResponse();
    }
}