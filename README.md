# TYPO3 Extension: Semantic Suggestion

## Join Our Community on Slack

We have a dedicated Slack channel where you can ask questions, discuss new features, and provide feedback on the extension. Join us to stay updated and participate in the conversation!

[Join the Slack Channel](https://typo3.slack.com/archives/C07HFM4364Q)

We look forward to seeing you there and engaging with you!


[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![Latest Stable Version](https://img.shields.io/packagist/v/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)
[![License](https://img.shields.io/packagist/l/talan-hdf/semantic-suggestion.svg)](https://packagist.org/packages/talan-hdf/semantic-suggestion)

> Elevate your TYPO3 website with intelligent, content-driven recommendations

## Introduction

The Semantic Suggestion extension revolutionizes the way related content is presented on TYPO3 websites. Moving beyond traditional "more like this" functionalities based on categories and taxonomies, this extension employs advanced semantic analysis to create genuinely relevant content connections.

### Key Benefits:

- 🎯 **Highly Relevant Links**: Automatically generate connections based on actual content similarity, not just predefined categories.
- ⏱️ **Increased User Engagement**: Keep visitors on your site longer by offering truly related content.
- 🕸️ **Semantic Cocoon**: Contribute to a high-quality semantic network within your website, enhancing SEO and user navigation.
- 🤖 **Intelligent Automation**: Reduce manual linking work while improving internal link quality.

### Performance Consideration

While the Semantic Suggestion extension offers powerful capabilities, it's important to note:

- 📊 The similarity calculation process scales exponentially with the number of pages.
- ⏳ For sites with over 500 pages, the initial calculation may take up to 30 seconds, depending on server capacity.
- 💡 We recommend using the backend module to assess performance for your specific setup.
- 🔄 Similarity scores are stored in the database and updated via a scheduler task, ensuring optimal performance.

> **Pro Tip**: Utilize the backend module to monitor performance and optimize settings for your specific use case.

By leveraging the power of semantic analysis, this extension provides a superior alternative to traditional related content plugins, offering more accurate and valuable content suggestions to your users.

---

## New in Version 2.0.0

### Database-Driven Storage
The extension now stores similarity scores in the database instead of TYPO3's cache system, providing:
- Improved persistence of calculated similarity data
- Better performance for large websites
- Reduced system overhead during page loads
- More resilient data storage that survives cache clearing operations

### Scheduler Task Integration
A new scheduler task is available to automatically update similarity scores:
- Schedule calculations during low-traffic periods
- Ensure up-to-date suggestions without affecting user experience
- Configure recalculation frequency based on your content update patterns
- Monitor calculation progress and performance through the scheduler interface

### Enhanced Performance
The new storage architecture delivers significant performance improvements:
- Faster page load times for sites with many pages
- Better resource utilization during similarity calculations
- Reduced CPU and memory usage during normal operation
- Smoother user experience, especially on large TYPO3 installations

### Stopwords Support
The extension now includes stopwords functionality, significantly improving the accuracy of content analysis. Stopwords are common words (such as "the", "is", "at") that are filtered out before processing the content. This feature enhances the relevance of semantic suggestions by focusing on meaningful content.

### Debug Mode
A new debug mode can be activated via TypoScript:

```typoscript
plugin.tx_semanticsuggestion_suggestions.settings.debugMode = 1
```

When enabled, this mode provides:
- Detailed debug information in the backend interface
- Comprehensive logs in public/typo3temp/logs/semantic_suggestion.log

This feature is invaluable for developers and administrators looking to fine-tune the extension's performance or troubleshoot issues.

## 📚 Table of Contents

- [Introduction](#-introduction)
- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Backend Module](#-backend-module)
- [Scheduler Task](#-scheduler-task)
- [Similarity Logic](#-similarity-logic)
- [Display Customization](#-display-customization)
- [Multilingual Support](#-multilingual-support)
- [Debugging and Maintenance](#-debugging-and-maintenance)
- [Security](#-security)
- [Performance](#-performance)
- [Unit Tests](#-unit-tests)
- [Contributing](#-contributing)
- [License](#-license)
- [Support](#-support)

## 🚀 Features

- 🔍 Analyzes subpages of a specified parent page
- 📊 Displays title, associated media, and enhanced text excerpt of suggested pages
- ⚙️ Highly configurable via TypoScript
- 🎛 Customizable parent page ID, proximity threshold, and search depth
- 💾 Database storage of similarity scores for optimal performance
- ⏱️ Scheduler task for automated similarity calculations
- 🌐 Built-in multilingual support
- 🧩 Improved compatibility with various TYPO3 content structures, including Bootstrap Package
- 🚫 Option to exclude specific pages from analysis and suggestions

## 🛠 Requirements

- TYPO3 12.0.0-13.9.99
- PHP 8.0 or higher

## 💻 Installation

<details>
<summary><strong>Composer Installation (recommended)</strong></summary>

1. Install the extension via composer:
   ```bash
   composer require talan-hdf/semantic-suggestion
   ```

2. Activate the extension in the TYPO3 Extension Manager
</details>

<details>
<summary><strong>Manual Installation</strong></summary>

1. Download the extension from the [TYPO3 Extension Repository (TER)](https://extensions.typo3.org/extension/semantic_suggestion) or the GitHub repository.
2. Upload the extension file to your TYPO3 installation's `typo3conf/ext/` directory.
3. In the TYPO3 backend, go to the Extension Manager and activate the "Semantic Suggestion" extension.
</details>

## ⚙️ Configuration

Edit your TypoScript setup to configure the extension:

```typoscript
plugin.tx_semanticsuggestion {
    settings {
        parentPageId = 1
        proximityThreshold = 0.7
        maxSuggestions = 3
        excerptLength = 150
        recursive = 1
        excludePages = 8,9,3456
        recencyWeight = 0.2

        analyzedFields {
            title = 1.5
            description = 1.0
            keywords = 2.0
            abstract = 1.2
            content = 1.0
        }
    }
}
```

### Weight System for Analyzed Fields

The `analyzedFields` section allows you to configure the importance of different content fields in the similarity calculation:

| Weight | Importance |
|--------|------------|
| 0.5    | Half as important as standard |
| 1.0    | Standard importance |
| 1.5    | 50% more important than standard |
| 2.0    | Twice as important as standard |
| 3.0+   | Significantly more important than standard |

<details>
<summary><strong>Configuration Parameters Explained</strong></summary>

- `parentPageId`: The ID of the parent page from which the analysis starts
- `proximityThreshold`: The minimum similarity threshold for displaying a suggestion (0.0 to 1.0)
- `maxSuggestions`: The maximum number of suggestions to display
- `excerptLength`: The maximum length of the text excerpt for each suggestion
- `recursive`: The search depth in the page tree (0 = only direct children)
- `excludePages`: Comma-separated list of page UIDs to exclude from analysis and suggestions
- `recencyWeight`: Weight of recency in similarity calculation (0-1) 
- `debugMode`: Enable detailed logging and debugging information (0 or 1)
</details>

### The Weight of Recency in Similarity Calculation (0-1)

The `recencyWeight` parameter determines the importance of publication or modification date in similarity calculations:

- **0:** Recency has no impact
- **1:** Recency has maximum impact

<details>
<summary><strong>How Recency Weight Works</strong></summary>

1. Base similarity score is calculated from content
2. Recency boost is calculated based on publication/modification dates
3. Final similarity is a weighted combination of content similarity and recency boost

Formula:
```
finalSimilarity = (contentSimilarity * (1 - recencyWeight)) + (recencyBoost * recencyWeight)
```

Choosing the right value:
- Low (0.1-0.3): Slightly favor recent content
- Medium (0.4-0.6): Balance between content similarity and recency
- High (0.7-0.9): Strongly favor recent content

Consider your specific use case:
- News website: Higher recency weight
- Educational resource: Lower recency weight
- General blog: Medium recency weight
</details>

## 🖥 Usage

### In Fluid Templates

To add the plugin directly in your Fluid template, use:

```html
<f:cObject typoscriptObjectPath='lib.semantic_suggestion' />
```

This method uses the TypoScript configuration and is suitable for simple integrations.

### TypoScript Integration

You can also integrate the Semantic Suggestions plugin using TypoScript. Add the following TypoScript setup to your configuration:

```typoscript
lib.semantic_suggestion = USER
lib.semantic_suggestion {
    userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
    extensionName = SemanticSuggestion
    pluginName = Suggestions
    vendorName = TalanHdf
    controller = Suggestions
    action = list
}
```

Then, you can use it in your TypoScript template like this:

```typoscript
page.10 = < lib.semantic_suggestion
```

Or in specific content elements:

```typoscript
tt_content.semantic_suggestion = COA
tt_content.semantic_suggestion {
    10 = < lib.semantic_suggestion
}
```

Remember to include your TypoScript template in your site configuration or page setup.

## 🎛 Backend Module

![Backend module](Documentation/Medias/backend_module.png) 

The Semantic Suggestion extension includes a powerful backend module providing comprehensive insights into semantic relationships between your pages.

### Features

- 📊 **Similarity Analysis**: Visualize semantic similarity between pages
- 🔝 **Top Similar Pairs**: Quickly identify most related page pairs
- 📈 **Distribution of Similarity Scores**: Overview of similarity across content
- ⚙️ **Configurable Analysis**: Set custom parameters (parent page ID, depth, thresholds)
- 📊 **Visual Representation**: Intuitive charts and progress bars
- 📑 **Detailed Statistics**: In-depth page similarity and content relationship data

Access the module under the "Web" menu in the TYPO3 backend.

> 💡 **Tip**: The effectiveness of semantic analysis depends on content quality and quantity. Ensure your pages have meaningful titles, descriptions, and content for best results.

## ⏱️ Scheduler Task

The extension now includes a scheduler task to automate similarity calculations:

### Features

- 🔄 **Automated Calculations**: Schedule similarity analysis to run automatically
- ⏰ **Configurable Frequency**: Set how often calculations should run (hourly, daily, weekly)
- 🎯 **Site-Specific Analysis**: Configure the task to focus on specific parent pages
- 💪 **Resource Management**: Run intensive calculations during off-peak hours
- 📝 **Detailed Logging**: Track calculation performance and issues through TYPO3's logging system

### Configuration

1. Go to **SCHEDULER** > **Add task**
2. Select **Semantic Suggestion: Generate Similarities**
3. Configure frequency and start time
4. Set parent page ID and recursive depth
5. Save and enable the task

> 💡 **Best Practice**: For large sites, schedule the task to run during off-peak hours to minimize impact on site performance.

## 🧮 Similarity Logic

The extension employs a custom similarity calculation to determine related pages:

1. **Data Gathering**: Collects title, description, keywords, and content for each subpage of the specified parent page.
2. **Similarity Calculation**: Compares page pairs using a word intersection and union method. The similarity score is the ratio of common words to total unique words, weighted by field importance.
3. **Proximity Threshold**: Only pages with similarity scores above the configured threshold are considered related and displayed.
4. **Database Storage**: Calculated scores are stored in the `tx_semanticsuggestion_similarities` table for optimal performance. These are updated based on your scheduler task configuration.

## 🎨 Display Customization

Customize the display of suggestions by overriding the Fluid template (List.html). Configure your own template paths in TypoScript:

```typoscript
plugin.tx_semanticsuggestion {
    view {
        templateRootPaths.10 = EXT:your_extension/Resources/Private/Templates/
    }
}
```

## 🌐 Multilingual Support

The extension fully supports TYPO3's multilingual structure, analyzing and suggesting pages in the current site language.

## 🐛 Debugging and Maintenance

The Semantic Suggestion extension utilizes TYPO3's logging system for comprehensive debugging and maintenance:

- 📝 Configure logging to get detailed information about the analysis and suggestion process
- 🔍 Monitor extension behavior and performance
- 🚀 Optimize based on logged data

<details>
<summary><strong>Configuring Logging</strong></summary>

Add the following to your `typo3conf/AdditionalConfiguration.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['TalanHdf']['SemanticSuggestion']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => 'typo3temp/logs/semantic_suggestion.log'
        ],
    ],
];
```

This configuration will log all debug-level and above messages to `semantic_suggestion.log`.
</details>

## 🔒 Security

The Semantic Suggestion extension implements several security measures:

- 🛡️ Protection against SQL injections through TYPO3's secure query mechanisms (QueryBuilder)
- 🔐 XSS attack prevention via automatic output escaping in Fluid templates
- 🚫 Access control restricted to users with appropriate permissions

## ⚡ Performance

Optimized for efficient operation, even with large numbers of pages:

- 💾 Database storage of similarity scores for optimal performance
- ⏱️ Scheduled background processing via the scheduler task
- 🚀 Optimized content retrieval process
- 🎯 Efficient handling of excluded pages
- ⚖️ Batch processing of page analysis for server load management

## 🧪 Unit Tests

The Semantic Suggestion extension includes a comprehensive suite of unit tests to ensure reliability and correctness of core functionalities, with a focus on the similarity calculation algorithm.

### Test Coverage

1. **Weighted Word Calculation**: Verifies the correct weighting of words based on field importance and word frequency.
2. **Similarity Calculation**: Ensures accuracy of page similarity calculations using cosine similarity.
3. **Field-Specific Similarity**: Tests the calculation of similarity scores for individual fields (title, content, keywords, etc.).
4. **Recency Boost Integration**: Validates the integration of recency factors in the final similarity score.
5. **Database Storage Integration**: Tests proper storage and retrieval of similarity data from the database.
6. **Scheduler Task Functionality**: Verifies the correct operation of the scheduler task.

## 🤝 Contributing

We welcome contributions to the Semantic Suggestion extension! Here's how you can contribute:

1. 🍴 Fork the repository
2. 🌿 Create a new branch for your feature or bug fix
3. 🛠️ Make your changes and commit them with clear messages
4. 🚀 Push your changes to your fork
5. 📬 Submit a pull request to the main repository

Please adhere to existing coding standards and include appropriate tests for your changes.

## 📄 License

This project is licensed under the GNU General Public License v2.0 or later. See the [LICENSE](LICENSE) file for full details.

## 🆘 Support

For support and further information:

👤 **Contact**:
   Wolfangel Cyril  
   Email: cyril.wolfangel@gmail.com

🐛 **Bug Reports and Feature Requests**:
   Use the [GitHub issue tracker](https://github.com/friteuseb/semantic_suggestion/issues)

📚 **Documentation and Updates**:
   Visit our [GitHub repository](https://github.com/friteuseb/semantic_suggestion)

---

📘 [Full Documentation](https://github.com/friteuseb/semantic_suggestion/wiki) | 🐛 [Report Bug](https://github.com/friteuseb/semantic_suggestion/issues) | 💡 [Request Feature](https://github.com/friteuseb/semantic_suggestion/issues)