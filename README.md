# TYPO3 Extension: Semantic Suggestion

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![Latest Stable Version](https://img.shields.io/packagist/v/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)
[![License](https://img.shields.io/packagist/l/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)

> Elevate your TYPO3 website with intelligent, content-driven recommendations.

## Introduction

The Semantic Suggestion extension revolutionizes the way related content is presented on TYPO3 websites. Moving beyond traditional category-based functionalities, this extension employs semantic analysis to create genuinely relevant content connections.

Since version 2.0.0, similarity scores are **stored in a dedicated database table** (`tx_semanticsuggestion_similarities`) instead of the TYPO3 cache. A **Scheduler task** handles calculation and storage, ensuring persistence and performance.

### Key Benefits:

-   **Highly Relevant Links**: Automatically generates connections based on actual content similarity.
-   **Increased User Engagement**: Keep visitors on your site longer by offering truly related content.
-   **Semantic Cocoon**: Contributes to a high-quality semantic network, enhancing SEO and navigation.
-   **Intelligent Automation**: Reduces manual linking work while improving internal link quality.

### Performance Considerations

-   The similarity calculation process performed by the Scheduler task can take time, especially on sites with a large number of pages (>500 pages might require 30s or more depending on the server).
-   Displaying suggestions and statistics (reading from the database) is optimized.
-   Use the backend module to assess the performance and relevance of suggestions for your specific setup.

---

## New in Version 2.0.0

### Database Storage
-   Similarity scores are now stored in the `tx_semanticsuggestion_similarities` table.
-   Enhanced data persistence (survives cache clearing).
-   Improved performance for large websites.

### Scheduler Task
-   A new Scheduler task (`Semantic Suggestion: Generate Similarities`) automates similarity calculation.
-   Configure the frequency and execution time (ideally during off-peak hours).
-   Easily maintain up-to-date suggestions without manual intervention.

### Stopwords Support and Debug Mode
-   Improved analysis with support for "stopwords" for multiple languages.
-   Added a debug mode toggleable via TypoScript (`plugin.tx_semanticsuggestion_suggestions.settings.debugMode = 1`) for development and troubleshooting.

## Table of Contents

-   [Introduction](#introduction)
-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
    -   [TypoScript Settings](#typoscript-settings-setuptyposcript)
    * [Scheduler Task Settings](#scheduler-task-settings)
    * [Configuration Interaction](#configuration-interaction)
-   [Usage (Frontend)](#usage-frontend)
-   [Backend Module](#backend-module)
-   [Scheduler Task](#scheduler-task-1)
-   [Similarity Logic (Simplified)](#similarity-logic-simplified)
-   [Display Customization](#display-customization)
-   [Multilingual Support](#multilingual-support)
-   [Debugging](#debugging)
-   [Contributing](#contributing)
-   [License](#license)
-   [Support](#support)

## Features

-   Analyzes subpages of a specified parent page via a Scheduler task.
-   Stores similarity scores in a database table (`tx_semanticsuggestion_similarities`).
-   Scheduler task to automate calculations and updates of similarities.
-   Displays suggestions (title, media, excerpt) on the frontend by reading from the database.
-   Backend module showing detailed statistics read from the database.
-   Highly configurable via TypoScript (display, analysis parameters) and Scheduler (analysis scope, storage threshold).
-   Built-in multilingual support.
-   Option to exclude specific pages from analysis (Scheduler) and/or display (TypoScript).

## Requirements

-   TYPO3 12.0.0 - 13.9.99
-   PHP 8.0 or higher

## Installation

<details>
<summary><strong>Composer Installation (recommended)</strong></summary>

1.  Install the extension:
    ```bash
    composer require talan-hdf/semantic-suggestion
    ```
2.  Activate the extension in the TYPO3 Extension Manager.

</details>

<details>
<summary><strong>Manual Installation</strong></summary>

1.  Download the extension from the [TER](https://extensions.typo3.org/extension/semantic_suggestion) or GitHub.
2.  Upload the archive to `typo3conf/ext/`.
3.  Activate the extension in the Extension Manager.

</details>

## Configuration

The extension's configuration is split between TypoScript settings and Scheduler task settings.

### TypoScript Settings (`setup.typoscript`)

These settings primarily control the **frontend display** and the **details of the analysis algorithm**. Define them in your TypoScript Setup file under `plugin.tx_semanticsuggestion_suggestions.settings`.

```typoscript
plugin.tx_semanticsuggestion_suggestions {
    settings {
        # --- Frontend Display Settings ---
        maxSuggestions = 5       # Maximum number of suggestions to display
        proximityThreshold = 0.5 # Minimum similarity threshold TO DISPLAY a suggestion (0.0 to 1.0)
        excerptLength = 150      # Max length of the text excerpt
        excludePages = 8,9,3456  # Pages to exclude from DISPLAY (comma-separated list of UIDs)

        # --- Analysis Algorithm Settings (Used by Scheduler task via PageAnalysisService) ---
        recencyWeight = 0.2      # Weight of recency in the final score (0.0 to 1.0)
        analyzedFields {         # Fields analyzed and their weights
            title = 1.5
            description = 1.0
            keywords = 2.0
            abstract = 1.2
            content = 1.0        # Content from the page's content elements
        }
        # defaultLanguage = 'en' # ISO language code (e.g., 'fr', 'en') to use if detection fails

        # --- Backend Module Settings (Optional) ---
        showStatistics = 1
        showPerformanceMetrics = 1
        showLanguageStatistics = 1
        # ... other show* settings

        # --- Debugging ---
        debugMode = 0            # Enable debug logs (0 or 1)
    }
    view {
        # Paths to your Fluid templates if you wish to customize them
        templateRootPaths.10 = EXT:your_extension/Resources/Private/Templates/
        partialRootPaths.10 = EXT:your_extension/Resources/Private/Partials/
        layoutRootPaths.10 = EXT:your_extension/Resources/Private/Layouts/
    }
}
````

### Scheduler Task Settings

These settings are defined directly in the TYPO3 **Scheduler** module interface when creating/editing the **"Semantic Suggestion: Generate Similarities"** task. They control the **analysis execution** and **what gets stored in the database**.

  - **`Start Page ID`**: The UID of the root page from which the subpage analysis will begin. This defines the scope of the analysis for this task run. Each task execution is linked to a `Start Page ID` (stored as `root_page_id` in the DB).
  - **`Pages to exclude`**: Comma-separated list of page UIDs that will **not be analyzed**, and their similarities will **not be stored**.
  - **`Minimum similarity threshold`**: Threshold (0.0 to 1.0) below which a pair of similar pages will **not be saved** to the database. This is the `minimumSimilarity` used for storage.

### Configuration Interaction

  - **Analysis Scope**: Defined by the Scheduler task's `Start Page ID`.
  - **DB Storage**: Controlled by the Scheduler task's `Minimum similarity threshold` and `Pages to exclude`.
  - **Similarity Calculation**: Performed by the `PageAnalysisService` (called by the Cron task), which uses the TypoScript settings `analyzedFields` and `recencyWeight`.
  - **Frontend Display**: Reads from the DB and filters/limits based on the TypoScript settings `proximityThreshold`, `maxSuggestions`, `excludePages`.
  - **Backend Display**: Reads from the DB (based on the selected `root_page_id`) and filters based on the TypoScript `proximityThreshold`.

**Key Points:**

  - The `proximityThreshold` (TypoScript) cannot display suggestions with a score lower than the `minimumSimilarity` (Scheduler) because they were not saved. For the TypoScript setting to be effective, it must be ≥ the Scheduler threshold.
  - A page excluded in the Scheduler will never be analyzed/stored. A page excluded *only* in TypoScript will be analyzed/stored (if not excluded in Scheduler) but not displayed. It's often simpler to keep the `excludePages` lists synchronized.
  - You can create **multiple Scheduler tasks** with different `Start Page IDs` to analyze different sections of the site.

## Usage (Frontend)

Integrate the plugin into your Fluid templates to display suggestions:

```html
<f:cObject typoscriptObjectPath='lib.semantic_suggestion' />
```

Ensure the following TypoScript is included in your setup:

```typoscript
lib.semantic_suggestion = USER
lib.semantic_suggestion {
    userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
    extensionName = SemanticSuggestion // or semantic_suggestion
    pluginName = Suggestions
    vendorName = TalanHdf // or your vendor name
    controller = Suggestions
    action = list

    settings =< plugin.tx_semanticsuggestion_suggestions.settings
    persistence =< plugin.tx_semanticsuggestion_suggestions.persistence
    view =< plugin.tx_semanticsuggestion_suggestions.view
}

# Optional: Include directly on a page or in a content element via TypoScript
# page.10 =< lib.semantic_suggestion
```

The plugin will read relevant suggestions for the current page from the database, applying filters defined in the TypoScript settings (`proximityThreshold`, `maxSuggestions`, `excludePages`).

## Backend Module

![Backend Module](Documentation/Medias/backend_module.png)

A backend module ("Semantic Suggestion" under "Web") allows visualizing the results of the analyses stored in the database.

### Features

  - **Analysis Selection**: Choose which analysis to view (based on the `Start Page ID` / `root_page_id` of executed Scheduler tasks).
  - **Detailed Statistics**: Most similar pairs, score distribution, pages with the most links, language statistics.
  - **Configuration Overview**: Reminder of the main parameters used (display threshold, etc.).
  - **Performance Metrics (Basic)**: Module load time, number of stored pairs for the selected analysis.

## Scheduler Task

The **"Semantic Suggestion: Generate Similarities"** task is essential for the extension's operation.

  - **Role**: Calculates similarities between pages (using `PageAnalysisService`) and saves relevant results (above the `minimumSimilarity` threshold) to the `tx_semanticsuggestion_similarities` table.
  - **Configuration**: Set the `Start Page ID`, `Pages to exclude`, and `Minimum similarity threshold` via the Scheduler interface.
  - **Frequency**: Schedule its execution regularly (e.g., daily, weekly) during off-peak hours to keep suggestions up-to-date without impacting site performance.

## Similarity Logic (Simplified)

1.  **Execution (Scheduler Task)**: The Scheduler task selects pages to analyze based on its `Start Page ID` and exclusions.
2.  **Analysis (`PageAnalysisService`)**: For each page pair, the service calculates a similarity score based on the content of fields defined in `analyzedFields` (TypoScript), considering their respective weights and stopwords. An adjustment based on recency (`recencyWeight` TypoScript) is applied.
3.  **Storage (Scheduler Task)**: The task saves pairs whose final score is greater than or equal to the `minimumSimilarity` (Scheduler) to the `tx_semanticsuggestion_similarities` table.
4.  **Display (Frontend/Backend)**: The modules read scores from the database and apply the `proximityThreshold` (TypoScript) for the final display.

## Display Customization

Modify the appearance of suggestions by overriding the plugin's Fluid template (`List.html`). Configure the paths to your custom templates in TypoScript (see Configuration section).

## Multilingual Support

The extension accounts for TYPO3's multilingual structure. The Scheduler task analyzes and stores similarities for each configured site language. The frontend displays suggestions corresponding to the current language.

## Debugging

  - Enable `debugMode = 1` in TypoScript settings to get detailed logs in `typo3temp/logs/semantic_suggestion.log`.
  - Monitor the execution of the Scheduler task in the corresponding backend module.

## Contributing

Contributions are welcome\! Fork the repository, create a branch, make your changes, and submit a Pull Request.

## License

This project is licensed under the GNU General Public License v2.0 or later. See the [LICENSE](LICENSE) file.

## Support

**Contact**: Wolfangel Cyril (cyril.wolfangel@gmail.com)
**Bugs & Features**: [GitHub Issues](https://github.com/friteuseb/semantic_suggestion/issues)
**Documentation & Updates**: [GitHub Repository](https://github.com/friteuseb/semantic_suggestion)

-----
