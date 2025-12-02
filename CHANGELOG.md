# Changelog

All notable changes to CS Sponsored Articles will be documented in this file.

## [1.1.2] - 2025-12-01

### Fixed
- Fixed bug where last article on paginated pages could incorrectly receive sponsored styling
- Resolved issue with injected content between article containers causing mismatched styling
- Fixed false matches from sidebar links, tag clouds, and related article widgets
- Completely rewrote container detection algorithm using container boundary tracking

### Changed
- Now only matches article title links with `itemprop="url"` attribute (semantic article links)
- Simplified code by replacing regex-based container patterns with CSS class-based detection
- Improved performance by targeting only the opening tag instead of matching entire container blocks
- Process replacements from end to start to avoid position shifting issues

## [1.1.0] - 2025-11-27

### Added
- Template Type selector in plugin options (Auto-detect, Cassiopeia, Template Creator CK, YooTheme, JoomlArt, Custom)
- Custom Container Class option for templates not in the list
- Support for Template Creator CK (`tck-article` class)
- Support for YooTheme (`uk-article` class)
- Support for JoomlArt templates (`ja-item`, `ja-blog-item` classes)
- Support for ID-prefixed article URLs (e.g., `/123-article-alias.html`)

### Changed
- Custom field group and field labels shortened to "Sponsored?"
- Custom field now defaults to hidden on frontend (Automatic Display = Do not display)
- Improved regex patterns for more accurate blog item container matching

### Fixed
- Field value (Yes/No) no longer displays on article frontend by default
- Correct article container detection when multiple articles on page

## [1.0.0] - 2025-11-27

### Added
- Initial release of CS Sponsored Articles plugin for Joomla 5
- Automatic creation of "Sponsored" custom field group and "Sponsored?" field on installation
- Yes/No toggle field using Joomla's native switcher UI
- Automatic detection of sponsored articles in blog views
- Adds `cs-sponsored-item` CSS class to blog item containers for sponsored articles
- Configurable background color for sponsored articles (default: #fff3cd)
- CSS injection to hide the custom field value display on frontend
- Support for Cassiopeia template blog layout
- Support for generic blog-item and article containers
- Joomla 5 Native plugin architecture with namespaces and dependency injection
