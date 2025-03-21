<?php

namespace TalanHdf\SemanticSuggestion\Service;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class LanguageService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected SiteFinder $siteFinder;
    protected Context $context;

    public function __construct(
        SiteFinder $siteFinder,
        Context $context,
        LogManager $logManager
    ) {
        $this->siteFinder = $siteFinder;
        $this->context = $context;
        $this->setLogger($logManager->getLogger(__CLASS__));
    }

    /**
     * Calcule les statistiques de langue pour les pages données
     *
     * @param array $pages Pages à analyser
     * @param array $siteLanguages Langues du site
     * @return array Statistiques de langue
     */
    public function getLanguageStatistics(array $pages, array $siteLanguages): array
    {
        $languageStats = [];
        $totalPages = 0;

        // Initialiser les statistiques pour chaque langue
        foreach ($siteLanguages as $language) {
            $languageId = $language->getLanguageId();
            $languageStats[$languageId] = [
                'count' => 0,
                'info' => [
                    'title' => $language->getTitle(),
                    'twoLetterIsoCode' => $language->getTwoLetterIsoCode(),
                    'flagIdentifier' => $language->getFlagIdentifier(),
                ],
            ];
        }

        // Compter les pages par langue
        foreach ($pages as $page) {
            $languageId = $page['sys_language_uid'] ?? 0;
            if (isset($languageStats[$languageId])) {
                $languageStats[$languageId]['count']++;
                $totalPages++;
            }
        }

        // Calculer les pourcentages
        foreach ($languageStats as &$stat) {
            $stat['percentage'] = ($totalPages > 0) ? ($stat['count'] / $totalPages) * 100 : 0;
        }

        return [
            'statistics' => $languageStats,
            'totalPages' => $totalPages
        ];
    }

    /**
     * Récupère les langues configurées pour un site donné
     *
     * @param int $pageId ID de la page
     * @return array Liste des langues du site
     */
    public function getSiteLanguages(int $pageId): array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            return $site->getLanguages();
        } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
            $this->logger->warning(
                sprintf('No site found for page ID %d. Using default language.', $pageId),
                ['exception' => $e]
            );
            return [
                new SiteLanguage(
                    0,
                    'en',
                    new NullSite(),
                    ['title' => 'Default', 'twoLetterIsoCode' => 'en', 'flagIdentifier' => 'en']
                )
            ];
        }
    }

    /**
     * Récupère toutes les langues configurées dans l'instance TYPO3
     *
     * @return array Liste de toutes les langues disponibles
     */
    public function getAllLanguages(): array
    {
        $languages = [];
        $sites = $this->siteFinder->getAllSites();

        foreach ($sites as $site) {
            foreach ($site->getAllLanguages() as $language) {
                $languageId = $language->getLanguageId();
                $languages[$languageId] = [
                    'title' => $language->getTitle(),
                    'twoLetterIsoCode' => $language->getTwoLetterIsoCode(),
                    'flagIdentifier' => $language->getFlagIdentifier(),
                ];
            }
        }

        // Assurer la présence de la langue par défaut
        if (!isset($languages[0])) {
            $defaultLanguage = $sites[array_key_first($sites)]->getDefaultLanguage();
            $languages[0] = [
                'title' => $defaultLanguage->getTitle(),
                'twoLetterIsoCode' => $defaultLanguage->getTwoLetterIsoCode(),
                'flagIdentifier' => $defaultLanguage->getFlagIdentifier(),
            ];
        }

        ksort($languages);
        return $languages;
    }

    /**
     * Récupère l'ID de la langue courante
     *
     * @return int ID de la langue courante
     */
    public function getCurrentLanguageUid(): int
    {
        return $this->context->getAspect('language')->getId();
    }
}