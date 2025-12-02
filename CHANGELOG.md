# Changelog

All notable changes to CS Sponsored Articles will be documented in this file.

## [1.1.2] - 2025-12-01

### 🐛 Fixed
- **Paginated pages bug** - Last article on paginated pages no longer incorrectly receives sponsored styling
- **Injected content issue** - Resolved mismatched styling caused by injected content between article containers
- **False positive matches** - Fixed incorrect matches from sidebar links, tag clouds, and related article widgets
- **Container detection** - Completely rewrote algorithm using container boundary tracking

### 🔧 Changed
- **Semantic link matching** - Now only matches article title links with `itemprop="url"` attribute
- **Simplified detection** - Replaced regex-based container patterns with CSS class-based detection
- **Performance improvement** - Targets only the opening tag instead of matching entire container blocks
- **Position handling** - Process replacements from end to start to avoid position shifting issues

## [1.1.0] - 2025-11-27

### 🚀 Added
- **Template Type selector** - Plugin options now include Auto-detect, Cassiopeia, Template Creator CK, YooTheme, JoomlArt, Custom
- **Custom Container Class** - Option for templates not in the predefined list
- **Template Creator CK support** - Detects `tck-article` class
- **YooTheme support** - Detects `uk-article` class
- **JoomlArt support** - Detects `ja-item` and `ja-blog-item` classes
- **ID-prefixed URLs** - Support for article URLs like `/123-article-alias.html`

### 🔧 Changed
- **Field labels** - Custom field group and field labels shortened to "Sponsored?"
- **Frontend display** - Custom field now defaults to hidden on frontend (Automatic Display = Do not display)
- **Regex patterns** - Improved patterns for more accurate blog item container matching

### 🐛 Fixed
- **Field visibility** - Field value (Yes/No) no longer displays on article frontend by default
- **Multi-article detection** - Correct article container detection when multiple articles on page

## [1.0.0] - 2025-11-27

### 🚀 Added
- **Initial release** - CS Sponsored Articles plugin for Joomla 5
- **Custom field creation** - Automatic creation of "Sponsored" custom field group and "Sponsored?" field on installation
- **Switcher UI** - Yes/No toggle field using Joomla's native switcher UI
- **Auto-detection** - Automatic detection of sponsored articles in blog views
- **CSS class injection** - Adds `cs-sponsored-item` CSS class to blog item containers for sponsored articles
- **Configurable styling** - Background color setting for sponsored articles (default: `#fff3cd`)
- **Field hiding** - CSS injection to hide the custom field value display on frontend
- **Cassiopeia support** - Support for Cassiopeia template blog layout
- **Generic container support** - Support for generic blog-item and article containers
- **Joomla 5 Native** - Modern plugin architecture with namespaces and dependency injection
