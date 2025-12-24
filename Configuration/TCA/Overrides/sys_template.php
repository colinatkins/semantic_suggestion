<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die('Access denied.');

// addStaticFile is deprecated since TYPO3 12.1 and removed in TYPO3 14
// TypoScript is now loaded via @import in ext_localconf.php
// This file is kept for TYPO3 12 backward compatibility only
$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
if ($versionInformation->getMajorVersion() < 13) {
    ExtensionManagementUtility::addStaticFile(
        'semantic_suggestion',
        'Configuration/TypoScript/',
        'Semantic Suggestion'
    );
}
