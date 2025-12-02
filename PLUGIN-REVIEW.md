# CS Sponsored Articles Plugin - Technical Review

## Overview

The **CS Sponsored Articles** plugin is a Joomla 5 native system plugin that automatically highlights sponsored content in blog/category views. This document summarizes the implementation approach and explains why it represents a modern, best-practice Joomla 5 solution.

---

## What the Plugin Does

1. **Automatic Custom Field Creation** - On installation, creates a "Sponsored?" radio field that editors can toggle when editing articles
2. **Visual Highlighting** - Automatically applies a customizable background color to sponsored articles in blog layouts
3. **Multi-Template Support** - Works with Cassiopeia, YooTheme, JoomlArt, Template Creator CK, and custom templates
4. **Clean Frontend** - Hides the custom field value from visitors while still applying the styling

---

## File Structure

```
cs-sponsored-articles/
├── sponsoredarticles.xml           # Extension manifest
├── script.php                      # Installation script (creates custom field)
├── services/
│   └── provider.php                # Dependency injection service provider
├── src/
│   └── Extension/
│       └── SponsoredArticles.php   # Main plugin class
└── language/
    └── en-GB/
        ├── plg_system_sponsoredarticles.ini
        └── plg_system_sponsoredarticles.sys.ini
```

---

## Why This is a Joomla 5 Native Solution

### 1. Modern Namespace Architecture

```php
namespace CyberSalt\Plugin\System\SponsoredArticles;
```

The plugin uses PSR-4 autoloading with a proper vendor namespace. Joomla 5 automatically maps `src/Extension/SponsoredArticles.php` to the declared namespace.

### 2. Dependency Injection Container

Instead of the legacy `JPlugin` approach, this plugin uses Joomla 5's service provider pattern:

```php
// services/provider.php
return new class implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Dependencies are injected, not hardcoded
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin = new SponsoredArticles($dispatcher, $config);
                $plugin->setApplication(Factory::getApplication());
                return $plugin;
            }
        );
    }
};
```

**Why this matters:** The DI container pattern makes the code testable, maintainable, and follows SOLID principles.

### 3. Event Subscriber Pattern

```php
class SponsoredArticles extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onAfterRender' => 'onAfterRender'];
    }

    public function onAfterRender(Event $event): void
    {
        // Event handling code
    }
}
```

**Why this matters:** Joomla 5 moved from magic method hooks (`onAfterRender()` as a public method) to explicit event subscription. This is more explicit, discoverable, and performant.

### 4. Modern MVC Factory Usage

The installation script uses Joomla 5's component boot system:

```php
$app->bootComponent('com_fields')
    ->getMVCFactory()
    ->createTable('Field', 'Administrator');
```

**Why this matters:** This is the modern way to interact with Joomla components - booting them properly ensures all dependencies are loaded.

### 5. Typed Event Parameters

```php
public function onAfterRender(Event $event): void
```

**Why this matters:** Joomla 5 uses strongly-typed event objects instead of passing parameters by reference with `func_get_args()`.

---

## Key Implementation Decisions

### Smart Container Detection

The plugin doesn't require users to know CSS selectors. It provides:

| Template | Auto-Detected Classes |
|----------|----------------------|
| Cassiopeia | `com-content-category-blog__item` |
| YooTheme | `uk-article` |
| JoomlArt | `ja-item`, `ja-blog-item` |
| Template Creator CK | `tck-article` |
| Generic | `blog-item`, `news-item`, `article-item`, `card` |

Auto-detect mode tries all patterns, ensuring broad compatibility.

### URL Pattern Matching

The regex handles multiple URL formats:

- `/article-alias.html`
- `/article-alias`
- `/123-article-alias.html` (ID-prefixed)
- `/article-alias?itemid=123` (with query strings)

### CSS Injection Strategy

```php
$css = '<style>.cs-sponsored-item { background-color: ' . $bgColor . ' !important; }';
$css .= '.field-entry.sponsored-article { display: none !important; }</style>';
$body = str_replace('</head>', $css . '</head>', $body);
```

The styling is injected once per page load, keeping performance optimal.

---

## Configuration Options

| Option | Purpose |
|--------|---------|
| **Background Color** | Color picker for sponsored item highlighting |
| **Template Type** | Auto-detect or select specific template |
| **Custom Container Class** | For unsupported templates |

---

## Database Query

The plugin efficiently queries sponsored articles:

```sql
SELECT c.alias
FROM #__content c
INNER JOIN #__fields_values fv ON fv.item_id = c.id
INNER JOIN #__fields f ON f.id = fv.field_id
WHERE c.state = 1
  AND f.name = 'sponsored-article'
  AND f.context = 'com_content.article'
  AND fv.value = '1'
```

---

## What Makes This "Native" vs. Legacy

| Aspect | Legacy Joomla 3/4 | This Plugin (Joomla 5 Native) |
|--------|-------------------|------------------------------|
| Class Loading | `jimport()` / manual includes | PSR-4 namespaced autoloading |
| Dependencies | Global singletons | Dependency injection container |
| Events | Magic methods | `SubscriberInterface` pattern |
| Plugin Base | `JPlugin` | `CMSPlugin` with typed events |
| Component Access | `JComponentHelper` | `$app->bootComponent()` |
| Configuration | XML-only | XML with showon conditions |

---

## Performance Considerations

1. **Early Exit** - Plugin skips processing for admin pages and non-HTML documents
2. **Single Query** - All sponsored aliases fetched in one database query
3. **Lazy CSS** - Styling only injected when page contains potential matches
4. **No External Dependencies** - Pure PHP, no JavaScript required

---

## Summary

This plugin demonstrates how to build a Joomla 5 extension "the right way":

- **Namespaced code** following PSR-4 standards
- **Service provider** for dependency injection
- **Event subscriber** pattern for explicit event handling
- **MVC factory** for component interactions
- **Clean separation** of installation logic, runtime logic, and configuration
- **Multi-template support** with sensible auto-detection
- **User-friendly configuration** with conditional field display

The result is maintainable, testable, and follows Joomla 5's architectural vision for extensions.

---

*Plugin Version: 1.1.0*
*Joomla Compatibility: 5.0+*
*PHP Requirement: 8.1+*
