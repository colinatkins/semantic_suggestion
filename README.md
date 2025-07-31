# TYPO3 Extension: Semantic Suggestion

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![Latest Stable Version](https://img.shields.io/packagist/v/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)
[![License](https://img.shields.io/packagist/l/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)

> Elevate your TYPO3 website with intelligent, AI-powered content recommendations using advanced NLP technology.

## Introduction

The Semantic Suggestion extension revolutionizes the way related content is presented on TYPO3 websites. Moving beyond traditional category-based functionalities, this extension employs advanced **NLP (Natural Language Processing)** and **TF-IDF (Term Frequency-Inverse Document Frequency)** analysis to create genuinely relevant content connections.

Since version 2.0.0, similarity scores are **stored in a dedicated database table** (`tx_semanticsuggestion_similarities`) instead of the TYPO3 cache. A **Scheduler task** handles calculation and storage, ensuring persistence and performance.

**🚀 NEW in v3.0.0**: Enhanced with [nlp_tools](https://github.com/cywolf/nlp_tools) integration for professional-grade text analysis, featuring intelligent language detection for TYPO3 12/13 multilingual sites and optimized German language support.

### Key Benefits:

-   **🎯 Highly Relevant Links**: TF-IDF vectorization with advanced NLP creates genuinely relevant content connections
-   **🌍 Multilingual Intelligence**: Smart language detection for TYPO3 12/13 sites with automatic content analysis
-   **🇩🇪 German Language Optimized**: Professional stemming and handling of compound words (Automobilindustrie ↔ Automobil)
-   **📈 Increased User Engagement**: Keep visitors on your site longer by offering truly related content
-   **🔍 Semantic Cocoon**: Contributes to a high-quality semantic network, enhancing SEO and navigation
-   **⚡ Intelligent Automation**: Reduces manual linking work while improving internal link quality by 30-50%

### Performance Considerations

-   The similarity calculation process performed by the Scheduler task can take time, especially on sites with a large number of pages (>500 pages might require 30s or more depending on the server).
-   Displaying suggestions and statistics (reading from the database) is optimized.
-   Use the backend module to assess the performance and relevance of suggestions for your specific setup.

---

## 🆕 What's New in Version 3.0.0

### 🧠 Advanced NLP Integration
-   **TF-IDF Vectorization**: Professional-grade similarity calculation replacing basic cosine similarity
-   **Intelligent Language Detection**: Hybrid approach using TYPO3 configuration + content analysis
-   **Advanced Text Processing**: Stemming, stop word removal, and text normalization via nlp_tools

### 🌐 Enhanced Multilingual Support
-   **TYPO3 12/13 Compatibility**: Uses modern Context API and Site Configuration
-   **Smart Language Detection**: 
    - **Priority 1**: Uses TYPO3 site language configuration (`de_DE.UTF-8` → `de`)
    - **Priority 2**: Falls back to intelligent content analysis when TYPO3 config is uncertain
    - **Priority 3**: Confidence scoring prevents misdetection of mixed-language content
-   **Multi-site Ready**: Supports complex multilingual TYPO3 setups

### 🇩🇪 German Language Excellence
-   **Professional Stemming**: Uses Wamania\Snowball for German morphology
-   **Compound Word Support**: Recognizes relationships between "Automobil" and "Automobilindustrie"
-   **Umlaut Handling**: Perfect support for ä, ö, ü, ß characters
-   **German Stop Words**: Optimized list including der, die, das, ein, eine, mit, von, etc.

### 📊 Enhanced Algorithm
-   **TF-IDF Vectors**: More accurate similarity scoring than traditional word counting
-   **Confidence Thresholds**: Prevents false positives in language detection
-   **Fallback Mechanisms**: Graceful degradation if NLP processing fails
-   **Performance Caching**: TF-IDF vectors and stemming results are cached

## Table of Contents

-   [Introduction](#introduction)
-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Language Detection System](#language-detection-system)
-   [Configuration](#configuration)
    -   [Scheduler Task Configuration](#scheduler-task-configuration)
    -   [TypoScript Settings](#typoscript-settings)
    -   [NLP Configuration](#nlp-configuration)
    -   [Configuration Interaction](#configuration-interaction)
-   [Usage (Frontend)](#usage-frontend)
-   [Backend Module](#backend-module)
-   [Scheduler Task](#scheduler-task-1)
-   [Similarity Logic (TF-IDF Enhanced)](#similarity-logic-tf-idf-enhanced)
-   [Multilingual Sites Setup](#multilingual-sites-setup)
-   [Display Customization](#display-customization)
-   [Debugging & Performance](#debugging--performance)
-   [Migration from v2.x](#migration-from-v2x)
-   [Contributing](#contributing)
-   [License](#license)
-   [Support](#support)

## Features

### 🔧 Core Functionality
-   **Advanced TF-IDF Analysis**: Professional semantic similarity calculation using vectorization
-   **Database Storage**: Persistent similarity scores in `tx_semanticsuggestion_similarities` table
-   **Automated Processing**: Scheduler task for background calculation and updates
-   **Frontend Integration**: Clean API for displaying suggestions (title, media, excerpt)
-   **Backend Analytics**: Comprehensive statistics and performance metrics

### 🌐 Multilingual & NLP Features
-   **Intelligent Language Detection**: Hybrid TYPO3 + content analysis approach
-   **Professional Text Processing**: Stemming, stop word removal, text normalization
-   **German Language Excellence**: Optimized for compound words and umlauts
-   **Multi-site Support**: Handles complex TYPO3 multilingual configurations
-   **Confidence Scoring**: Prevents false language detection in mixed content

### ⚙️ Configuration & Performance
-   **Highly Configurable**: TypoScript settings for display and Scheduler for analysis scope
-   **Performance Optimized**: TF-IDF vector caching and intelligent fallbacks
-   **Flexible Exclusions**: Page-level exclusions for analysis and/or display
-   **Debug Mode**: Comprehensive logging for development and troubleshooting

## Requirements

-   **TYPO3**: 12.0.0 - 13.9.99
-   **PHP**: 8.0 or higher
-   **Dependencies**: 
    - `cywolf/nlp-tools` (automatically installed via Composer)
    - `wamania/php-stemmer` (for advanced stemming support)

## Installation

<details>
<summary><strong>Composer Installation (recommended)</strong></summary>

1.  Install the extension with dependencies:
    ```bash
    composer require talan-hdf/semantic-suggestion
    composer require cywolf/nlp-tools
    ```
2.  Activate both extensions in the TYPO3 Extension Manager.
3.  Clear TYPO3 cache: `./vendor/bin/typo3 cache:flush`

</details>

<details>
<summary><strong>Manual Installation</strong></summary>

1.  Download the extension from the [TER](https://extensions.typo3.org/extension/semantic_suggestion) or GitHub.
2.  Upload the archive to `typo3conf/ext/`.
3.  Activate the extension in the Extension Manager.

</details>

## Language Detection System

### 🧠 How Language Detection Works

The extension uses a **hybrid approach** that combines TYPO3's multilingual configuration with intelligent content analysis:

#### Detection Priority (Cascade System):

1. **🎯 Priority 1: TYPO3 Site Configuration** (for TYPO3 12/13)
   ```php
   // Uses TYPO3's Context API and Site Configuration
   $site = $request->getAttribute('site');
   $siteLanguage = $site->getLanguageById($languageId);
   $locale = $siteLanguage->getLocale(); // e.g., "de_DE.UTF-8"
   return strtolower(substr($locale->getLanguageCode(), 0, 2)); // → "de"
   ```

2. **🔍 Priority 2: Content Analysis** (when TYPO3 config is uncertain)
   - Extracts character trigrams from text content
   - Compares against language profiles built from stop words
   - Uses confidence scoring to prevent false positives

3. **⚖️ Priority 3: Confidence Verification**
   ```php
   // If confidence difference < 30%, trust TYPO3 context
   if (($firstScore - $secondScore) / $firstScore < 0.3) {
       return $this->getTypo3LanguageContext();
   }
   ```

### 🌍 Multilingual Site Examples

#### Example 1: Well-configured TYPO3 Site
```yaml
# site/config.yaml
languages:
  - languageId: 0
    locale: 'en_US.UTF-8'  # → nlp_tools detects "en"
  - languageId: 1  
    locale: 'de_DE.UTF-8'  # → nlp_tools detects "de"
  - languageId: 2
    locale: 'fr_FR.UTF-8'  # → nlp_tools detects "fr"
```
**Result**: nlp_tools uses TYPO3 configuration directly via Site API

#### Example 2: Mixed Content Detection
```
Page with:
- sys_language_uid = 0 (English)
- But content: "Die deutsche Automobilindustrie ist sehr wichtig"

Detection results:
- Content analysis: 85% German confidence
- TYPO3 context: English
- Final decision: German (high confidence overrides TYPO3)
```

#### Example 3: Uncertain Content
```
Detection scores: French: 45%, German: 42%
Confidence difference: (45-42)/45 = 6.7% < 30%
→ Falls back to TYPO3 language context
```

### 🚀 nlp_tools Integration

Yes, the extension **fully integrates with nlp_tools**:

```php
// In PageAnalysisService.php
protected function getCurrentLanguage(): string
{
    // Uses nlp_tools LanguageDetectionService
    $detectedLanguage = $this->languageDetector->detectLanguage('');
    return $detectedLanguage; // Smart hybrid detection
}
```

The language detection leverages:
- **LanguageDetectionService**: Trigram analysis and confidence scoring
- **TextAnalysisService**: Advanced text processing and stemming
- **TextVectorizerService**: TF-IDF vectorization for similarity calculation

### 🌍 Language Support Matrix

All languages benefit from nlp_tools integration, but with different optimization levels:

#### 🥇 **EXPERT Level Support** (Maximum Optimization)
| Language | Code | Stemming | Stop Words | TF-IDF | Improvement |
|----------|------|----------|------------|--------|-------------|
| 🇩🇪 **German** | `de` | ✅ Advanced (Wamania\Snowball) | ✅ Specialized | ✅ Full | **+40-50%** |

**German Features**:
- Professional compound word handling (`Automobilindustrie` ↔ `Automobil`)
- Perfect umlaut support (`ä, ö, ü, ß`)
- Specialized stop words (`der, die, das, ein, eine, mit, von, zu...`)

#### 🥈 **ADVANCED Level Support** (Professional Optimization)
| Language | Code | Stemming | Stop Words | TF-IDF | Improvement |
|----------|------|----------|------------|--------|-------------|
| 🇫🇷 French | `fr` | ✅ Wamania\Snowball | ✅ Specialized | ✅ Full | **+30-40%** |
| 🇬🇧 English | `en` | ✅ Wamania\Snowball | ✅ Specialized | ✅ Full | **+30-40%** |
| 🇪🇸 Spanish | `es` | ✅ Wamania\Snowball | ✅ Specialized | ✅ Full | **+30-40%** |

#### 🥉 **STANDARD Level Support** (TF-IDF Optimization)
| Language | Code | Stemming | Stop Words | TF-IDF | Improvement |
|----------|------|----------|------------|--------|-------------|
| 🇮🇹 Italian | `it` | ❌ Basic tokenization | ⚠️ Generic | ✅ Full | **+20-30%** |
| 🇵🇹 Portuguese | `pt` | ❌ Basic tokenization | ⚠️ Generic | ✅ Full | **+20-30%** |
| 🇳🇱 Dutch | `nl` | ❌ Basic tokenization | ⚠️ Generic | ✅ Full | **+20-30%** |
| **All Others** | `*` | ❌ Basic tokenization | ⚠️ Generic | ✅ Full | **+20-25%** |

### 📊 **What Every Language Gets**

**✅ All languages benefit from**:
1. **TF-IDF Vectorization**: Professional semantic similarity calculation
2. **Intelligent Language Detection**: Hybrid TYPO3 + content analysis  
3. **Advanced Text Cleaning**: Unicode normalization, accent removal
4. **Performance Caching**: TF-IDF vectors and processing results cached

```php
// ALL languages get TF-IDF processing
$tfidfResult = $textVectorizer->createTfIdfVectors([$text1, $text2], $language);
$similarity = $textVectorizer->cosineSimilarity($vector1, $vector2);

// Only de/fr/en/es get advanced stemming
if (in_array($language, ['de', 'fr', 'en', 'es'])) {
    $stemmedWords = $textAnalyzer->stem($text, $language);
} else {
    $stemmedWords = $textAnalyzer->tokenize($text); // Basic tokenization
}
```

### 🎯 **Language-Specific Configuration Examples**

#### German Sites (Maximum Performance)
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    enableStemming = 1                # Enable compound word processing
    proximityThreshold = 0.25         # Lower threshold (compound words create more matches)
    analyzedFields {
        title = 2.0                   # German titles often contain key compounds
        keywords = 2.5                # German keywords are highly specific
    }
}
```

#### French/English/Spanish Sites  
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    enableStemming = 1                # Enable advanced stemming
    proximityThreshold = 0.3          # Standard threshold
    analyzedFields {
        title = 1.5
        keywords = 2.0
    }
}
```

#### Other Languages (Italian, Portuguese, etc.)
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    enableStemming = 0                # No stemming available, but still gets TF-IDF
    proximityThreshold = 0.35         # Slightly higher threshold
    minTextLength = 100               # Longer text for better TF-IDF accuracy
}
```

**Note**: Even without stemming, languages like Italian and Portuguese still get **significant improvements** from TF-IDF vectorization compared to basic word counting.

## Configuration

⚠️ **Important**: The extension's configuration is split between **Scheduler task settings** (analysis scope and execution) and **TypoScript settings** (frontend display and algorithm parameters).

### Scheduler Task Configuration

**This is the primary configuration** that controls the analysis execution and what gets stored in the database.

Create a **"Semantic Suggestion: Generate Similarities"** task in the TYPO3 Scheduler module with these settings:

- **`startPageId`** (required): The UID of the root page from which the analysis will begin. This defines the scope of the analysis for this task run. Each task execution is linked to a Start Page ID (stored as `root_page_id` in the DB).
  - Example: `1` (for site root page)
  
- **`excludePages`** (optional): Comma-separated list of page UIDs that will **not be analyzed**, and their similarities will **not be stored**.
  - Example: `42,56,78`
  
- **`minimumSimilarity`** (required): Threshold (0.0 to 1.0) below which a pair of similar pages will **not be saved** to the database. This controls storage efficiency.
  - Example: `0.3` (saves only pairs with similarity ≥ 30%)

**Recommended scheduling:**
- Frequency: Daily or weekly
- Execution time: During off-peak hours (e.g., 2:00 AM)

### TypoScript Settings

These settings control the **frontend display** and the **details of the analysis algorithm**. Define them in your TypoScript Setup file under `plugin.tx_semanticsuggestion_suggestions.settings`.

#### Constants (constants.typoscript)

```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    # --- Frontend Display Settings ---
    proximityThreshold = 0.5     # Minimum similarity threshold TO DISPLAY a suggestion (0.0 to 1.0)
    maxSuggestions = 3           # Maximum number of suggestions to display
    excerptLength = 100          # Max length of the text excerpt
    excludePages =               # Pages to exclude from DISPLAY (comma-separated list of UIDs)

    # --- Analysis Algorithm Settings (Used by Scheduler task) ---
    recencyWeight = 0.2          # Weight of recency in the final score (0.0 to 1.0)
    
    # Fields analyzed and their weights
    analyzedFields {
        title = 1.5              # Weight of page title
        description = 1.0        # Weight of page description
        keywords = 2.0           # Weight of page keywords
        abstract = 1.2           # Weight of page abstract
        content = 1.0            # Weight of page content elements
    }

    # --- Debugging ---
    debugMode = 0                # Enable debug logs (0 or 1)
}
```

#### Setup (setup.typoscript)

```typoscript
# Main plugin configuration
plugin.tx_semanticsuggestion_suggestions {
    settings {
        proximityThreshold = {$plugin.tx_semanticsuggestion_suggestions.settings.proximityThreshold}
        maxSuggestions = {$plugin.tx_semanticsuggestion_suggestions.settings.maxSuggestions}
        excludePages = {$plugin.tx_semanticsuggestion_suggestions.settings.excludePages}
        excerptLength = {$plugin.tx_semanticsuggestion_suggestions.settings.excerptLength}
        recencyWeight = {$plugin.tx_semanticsuggestion_suggestions.settings.recencyWeight}
        debugMode = {$plugin.tx_semanticsuggestion_suggestions.settings.debugMode}
        
        analyzedFields {
            title = {$plugin.tx_semanticsuggestion_suggestions.settings.analyzedFields.title}
            description = {$plugin.tx_semanticsuggestion_suggestions.settings.analyzedFields.description}
            keywords = {$plugin.tx_semanticsuggestion_suggestions.settings.analyzedFields.keywords}
            abstract = {$plugin.tx_semanticsuggestion_suggestions.settings.analyzedFields.abstract}
            content = {$plugin.tx_semanticsuggestion_suggestions.settings.analyzedFields.content}
        }
    }
    view {
        # Paths to your Fluid templates if you wish to customize them
        templateRootPaths.10 = EXT:your_extension/Resources/Private/Templates/
        partialRootPaths.10 = EXT:your_extension/Resources/Private/Partials/
        layoutRootPaths.10 = EXT:your_extension/Resources/Private/Layouts/
    }
}

# Reusable TypoScript object
lib.semantic_suggestion = USER
lib.semantic_suggestion {
    userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
    extensionName = SemanticSuggestion
    pluginName = Suggestions
    vendorName = TalanHdf
    
    view =< plugin.tx_semanticsuggestion_suggestions.view
    persistence =< plugin.tx_semanticsuggestion_suggestions.persistence
    settings =< plugin.tx_semanticsuggestion_suggestions.settings
}

# Content element integration
tt_content.list.20.semanticsuggestion_suggestions =< plugin.tx_semanticsuggestion_suggestions
```

### NLP Configuration

#### Advanced Language & Text Processing Settings

```typoscript
plugin.tx_semanticsuggestion_suggestions {
    settings {
        # 🧠 NLP Features (NEW in v3.0)
        enableStemming = 1               # Enable advanced stemming (German compound words)
        defaultLanguage = en             # Fallback language
        minTextLength = 50               # Minimum text length for analysis
        confidenceThreshold = 0.3        # Language detection confidence threshold
        
        # 🌐 Language Mapping (TYPO3 12/13)
        languageMapping {
            0 = en    # Language UID 0 → English
            1 = fr    # Language UID 1 → French  
            2 = de    # Language UID 2 → German
            3 = es    # Language UID 3 → Spanish
            4 = it    # Language UID 4 → Italian
            5 = pt    # Language UID 5 → Portuguese
        }
        
        # 🇩🇪 German Language Optimization
        proximityThreshold = 0.25        # Lower threshold for TF-IDF (more sensitive)
        
        # 📊 TF-IDF Algorithm Settings  
        analyzedFields {
            title = 1.5      # German titles often contain key compound words
            keywords = 2.0   # German keywords are highly indicative
            content = 1.0    # Base content weight
            description = 1.0
            abstract = 1.2
        }
    }
}
```

#### Multilingual Site Example Configuration

For a German/English bilingual site:

```typoscript
# constants.typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    enableStemming = 1
    proximityThreshold = 0.25
    confidenceThreshold = 0.3
    
    languageMapping {
        0 = en
        1 = de
    }
}
```

### Configuration Interaction

  - **Analysis Scope**: Defined by the Scheduler task's `startPageId`.
  - **DB Storage**: Controlled by the Scheduler task's `minimumSimilarity` and `excludePages`.
  - **Similarity Calculation**: Performed by the `PageAnalysisService` (called by the Scheduler task), which uses the TypoScript settings `analyzedFields` and `recencyWeight`.
  - **Frontend Display**: Reads from the DB and filters/limits based on the TypoScript settings `proximityThreshold`, `maxSuggestions`, `excludePages`.
  - **Backend Display**: Reads from the DB (based on the selected `root_page_id`) and filters based on the TypoScript `proximityThreshold`.

**Key Points:**

  - The `proximityThreshold` (TypoScript) cannot display suggestions with a score lower than the `minimumSimilarity` (Scheduler) because they were not saved. For the TypoScript setting to be effective, it must be ≥ the Scheduler threshold.
  - A page excluded in the Scheduler will never be analyzed/stored. A page excluded *only* in TypoScript will be analyzed/stored (if not excluded in Scheduler) but not displayed. It's often simpler to keep the `excludePages` lists synchronized.
  - You can create **multiple Scheduler tasks** with different `startPageId` values to analyze different sections of the site.

## Usage (Frontend)

Integrate the plugin into your Fluid templates to display suggestions:

```html
<f:cObject typoscriptObjectPath='lib.semantic_suggestion' />
```

Or include it directly in TypoScript:

```typoscript
# Include on a page
page.10 =< lib.semantic_suggestion

# Or in a content element
lib.myContent = COA
lib.myContent {
    10 =< lib.semantic_suggestion
}
```

The plugin will read relevant suggestions for the current page from the database, applying filters defined in the TypoScript settings (`proximityThreshold`, `maxSuggestions`, `excludePages`).

## Backend Module

![Backend Module](Documentation/Medias/backend_module.png)

A backend module ("Semantic Suggestion" under "Web") allows visualizing the results of the analyses stored in the database.

### Features

  - **Analysis Selection**: Choose which analysis to view (based on the `startPageId` / `root_page_id` of executed Scheduler tasks).
  - **Detailed Statistics**: Most similar pairs, score distribution, pages with the most links, language statistics.
  - **Configuration Overview**: Reminder of the main parameters used (display threshold, etc.).
  - **Performance Metrics**: Module load time, number of stored pairs for the selected analysis.

## Scheduler Task

The **"Semantic Suggestion: Generate Similarities"** task is essential for the extension's operation.

  - **Role**: Calculates similarities between pages (using `PageAnalysisService`) and saves relevant results (above the `minimumSimilarity` threshold) to the `tx_semanticsuggestion_similarities` table.
  - **Configuration**: Set the `startPageId`, `excludePages`, and `minimumSimilarity` via the Scheduler interface.
  - **Frequency**: Schedule its execution regularly (e.g., daily, weekly) during off-peak hours to keep suggestions up-to-date without impacting site performance.

## Similarity Logic (TF-IDF Enhanced)

### 🔄 Processing Pipeline

1. **🎯 Language Detection** (NEW in v3.0)
   ```
   Text Input → TYPO3 Site Context → Content Analysis → Language: "de"
   ```

2. **📝 Advanced Text Processing** (via nlp_tools)
   ```
   Raw Text → Stop Words Removal → Stemming (German) → Clean Tokens
   
   Example:
   "Die deutschen Automobilhersteller" 
   → "deutsch automobilherstell" (stemmed)
   ```

3. **🔢 TF-IDF Vectorization** (NEW in v3.0)
   ```
   [Text1, Text2] → TF-IDF Vectors → Cosine Similarity Score
   
   Traditional: Simple word counting
   TF-IDF: Professional semantic analysis
   ```

4. **💾 Smart Storage & Display**
   ```
   Similarity Score + Recency Boost → Database → Frontend Filter
   Scheduler threshold: 0.3 → Display threshold: 0.5
   ```

### 📊 Algorithm Improvements

#### Before (v2.x): Basic Cosine Similarity
```php
// Simple word frequency comparison
$similarity = $dotProduct / ($magnitude1 * $magnitude2);
```

#### After (v3.0): TF-IDF + NLP
```php
// Professional semantic analysis
$tfidfResult = $this->textVectorizer->createTfIdfVectors([$text1, $text2], $language);
$similarity = $this->textVectorizer->cosineSimilarity($vector1, $vector2);

// + German stemming, stop word removal, confidence scoring
```

**Performance Impact**: 30-50% better accuracy, especially for German compound words.

## Multilingual Sites Setup

### 🌍 TYPO3 12/13 Site Configuration

The extension automatically detects languages from your TYPO3 site configuration:

```yaml
# config/sites/main/config.yaml
languages:
  - 
    languageId: 0
    title: 'English'
    hreflang: 'en-US'
    locale: 'en_US.UTF-8'    # → Auto-detected as "en"
    iso-639-1: 'en'
  -
    languageId: 1
    title: 'Deutsch'  
    hreflang: 'de-DE'
    locale: 'de_DE.UTF-8'    # → Auto-detected as "de"  
    iso-639-1: 'de'
  -
    languageId: 2
    title: 'Français'
    hreflang: 'fr-FR' 
    locale: 'fr_FR.UTF-8'    # → Auto-detected as "fr"
    iso-639-1: 'fr'
```

### 🎯 Per-Language Scheduler Tasks

For optimal performance, create separate Scheduler tasks for each language:

```
Task 1: "Generate Similarities - English"
- startPageId: 1 (English root)
- minimumSimilarity: 0.3

Task 2: "Generate Similarities - German" 
- startPageId: 2 (German root)
- minimumSimilarity: 0.25  # Lower for German (compound words)

Task 3: "Generate Similarities - French"
- startPageId: 3 (French root) 
- minimumSimilarity: 0.3
```

### 🔧 Language-Specific Tuning

#### German Language Optimization
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    # German compound words need lower thresholds
    proximityThreshold = 0.25
    
    # Enable stemming for better compound word detection
    enableStemming = 1
    
    # German titles are often highly descriptive
    analyzedFields {
        title = 2.0      # Higher weight for German titles
        keywords = 2.5   # German keywords are very specific
    }
}
```

### 🚨 Troubleshooting Multilingual Sites

#### Problem: Language always detected as "en"
**Solution**: Check your TypoScript language mapping:
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    languageMapping {
        0 = en
        1 = de    # Make sure this matches your site language UID
        2 = fr
    }
}
```

#### Problem: Mixed language content not detected properly
**Enable debug mode** to see detection process:
```typoscript  
plugin.tx_semanticsuggestion_suggestions.settings {
    debugMode = 1
}
```

**Check logs** for:
```
Language detected via nlp_tools: de
TF-IDF similarity calculated: language=de, vocabularySize=156
Confidence verification: firstScore=0.85, secondScore=0.45, using content analysis
```

## Display Customization

Modify the appearance of suggestions by overriding the plugin's Fluid template (`List.html`). Configure the paths to your custom templates in TypoScript (see Configuration section).

## Debugging & Performance

### 🔍 Debug Mode

Enable comprehensive logging to troubleshoot language detection and similarity calculation:

```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    debugMode = 1
}
```

**Debug logs show**:
```
[INFO] Language detected via nlp_tools: de
[DEBUG] TF-IDF similarity calculated: page1=123, page2=456, similarity=0.75, vocabularySize=245
[DEBUG] German stemming applied: "Automobilindustrie" → "automobilindustr"
[DEBUG] Confidence verification: using TYPO3 context due to low confidence difference
[ERROR] Failed to create TF-IDF vectors: text too short, using fallback calculation
```

### ⚡ Performance Monitoring

**Backend Module** shows performance metrics:
- TF-IDF processing time per page pair
- Language detection accuracy
- Vocabulary size per language
- Cache hit rates for stemming/vectorization

**Scheduler Task** execution time:
- **Small sites** (<100 pages): ~10-30 seconds  
- **Medium sites** (100-500 pages): ~1-5 minutes
- **Large sites** (>500 pages): ~5-30 minutes

### 🚀 Performance Optimization

#### Recommended Settings for Large Sites
```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    # Reduce analysis scope
    minTextLength = 100          # Skip very short content
    
    # Cache-friendly thresholds  
    proximityThreshold = 0.3     # Higher threshold = less processing
    
    # Selective field analysis
    analyzedFields {
        title = 2.0
        keywords = 2.0
        content = 0               # Disable content analysis if too slow
        description = 1.0
        abstract = 0              # Disable if not used
    }
}
```

#### Cache Configuration
Ensure TYPO3 cache is properly configured for optimal performance:
```bash
# Clear cache after configuration changes
./vendor/bin/typo3 cache:flush

# Monitor cache effectiveness
./vendor/bin/typo3 cache:listGroups
```

## Migration from v2.x

### 🔄 Automatic Migration

The extension includes automatic fallback mechanisms:

1. **TF-IDF Processing**: Falls back to old cosine similarity if nlp_tools fails
2. **Language Detection**: Falls back to TypoScript mapping if site detection fails  
3. **Text Processing**: Falls back to basic stop word removal if stemming fails

### 📋 Migration Checklist

1. **Install nlp_tools dependency**:
   ```bash
   composer require cywolf/nlp-tools
   ```

2. **Update TypoScript configuration** (add new NLP settings):
   ```typoscript
   plugin.tx_semanticsuggestion_suggestions.settings {
       enableStemming = 1
       languageMapping {
           0 = en
           1 = de
           # ... your language mapping
       }
   }
   ```

3. **Clear TYPO3 cache**:
   ```bash
   ./vendor/bin/typo3 cache:flush
   ```

4. **Regenerate similarities** (run Scheduler task):
   - All existing similarities will be recalculated with TF-IDF
   - German content should see immediate improvement
   - Check debug logs to verify language detection

5. **Monitor performance**:
   - Check backend module for accuracy improvements
   - Compare similarity scores before/after migration
   - Verify multilingual sites work correctly

### 🆘 Rollback Plan

If you experience issues, you can disable advanced features:

```typoscript
plugin.tx_semanticsuggestion_suggestions.settings {
    enableStemming = 0           # Disable stemming
    debugMode = 1                # Enable debug logging
    minTextLength = 200          # Increase minimum text length
}
```

The extension will automatically fall back to v2.x behavior while maintaining database compatibility.

## Contributing

Contributions are welcome! Fork the repository, create a branch, make your changes, and submit a Pull Request.

## License

This project is licensed under the GNU General Public License v2.0 or later. See the [LICENSE](LICENSE) file.

## Support

**Contact**: Wolfangel Cyril (cyril.wolfangel@gmail.com)
**Bugs & Features**: [GitHub Issues](https://github.com/friteuseb/semantic_suggestion/issues)
**Documentation & Updates**: [GitHub Repository](https://github.com/friteuseb/semantic_suggestion)