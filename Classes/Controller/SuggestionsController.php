<?php
namespace TalanHdf\SemanticSuggestion\Controller;

// --- Imports ---
use Psr\Http\Message\ResponseInterface;
use TalanHdf\SemanticSuggestion\Service\SuggestionService;
use TalanHdf\SemanticSuggestion\Service\UtilityService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService; // Assurez-vous que c'est utilisé ou supprimez
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController; // Importer ActionController

class SuggestionsController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_PAGE = 10;

    // Garder les propriétés nécessaires
    protected PageAnalysisService $pageAnalysisService;
    protected SuggestionService $suggestionService;
    protected UtilityService $utility;

    // Garder le constructeur
    public function __construct(
        PageAnalysisService $pageAnalysisService,
        SuggestionService $suggestionService,
        UtilityService $utility
    ) {
        $this->pageAnalysisService = $pageAnalysisService;
        $this->suggestionService = $suggestionService;
        $this->utility = $utility;
        // L'injection du logger se fait via LoggerAwareTrait/Interface
        // $this->setLogger(...) // Est appelé automatiquement si configuré dans Services.yaml
    }

    // Garder listAction() et les autres méthodes métier
    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        // Log via le logger injecté (assurez-vous que l'injection fonctionne via Services.yaml et setLogger)
        $this->logger->debug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        // Utiliser $GLOBALS['TSFE'] directement peut être moins fiable en v12/v13,
        // envisagez de récupérer l'ID de page via $this->request si possible,
        // mais pour l'instant gardons $GLOBALS['TSFE']->id
        $currentPageId = (int)($GLOBALS['TSFE']->id ?? 0);
        if ($currentPageId === 0) {
             $this->logger->error('Could not determine current page ID in listAction');
             // Gérer l'erreur, peut-être retourner une réponse vide ou un message
             return $this->htmlResponse('Error: Could not determine current page ID.');
        }

        $currentLanguageUid = $this->utility->getCurrentLanguageUid(); // Assurez-vous que UtilityService fonctionne
        $viewData = $this->suggestionService->generateSuggestionsFromDatabase($currentPageId, $currentPage, $itemsPerPage);

        // Ajouter les logs de debug aux données de la vue si nécessaire
        $viewData['debugLogs'] = $this->utility->getDebugLogs();

        $this->view->assignMultiple($viewData);
        return $this->htmlResponse();
    }
}