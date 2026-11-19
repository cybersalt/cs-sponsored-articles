<?php
/**
 * @package     CyberSalt\Plugin\System\SponsoredArticles
 * @copyright   Copyright (C) 2025 CyberSalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace CyberSalt\Plugin\System\SponsoredArticles\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\Event\SubscriberInterface;
use Joomla\Event\Event;

/**
 * CS Sponsored Articles Plugin
 *
 * Automatically detects articles with a custom field 'sponsored-article' set to 1/yes
 * and adds a 'sponsored-article' CSS class to style them differently in blog views.
 */
class SponsoredArticles extends CMSPlugin implements SubscriberInterface
{
    /**
     * Background colour used when the setting is empty or invalid.
     */
    private const DEFAULT_BACKGROUND = '#fff3cd';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'onBeforeCompileHead',
            'onAfterRender'       => 'onAfterRender',
        ];
    }

    /**
     * Adds the plugin's front-end CSS through the Web Asset Manager.
     *
     * The plugin's own style is added first and the Custom CSS second, declared as
     * depending on the first, so the Custom CSS is always output after it and wins.
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     */
    public function onBeforeCompileHead(Event $event): void
    {
        $app = $this->getApplication();

        // Only run on site (not admin)
        if ($app->isClient('administrator')) {
            return;
        }

        $document = $app->getDocument();

        if (!($document instanceof HtmlDocument)) {
            return;
        }

        $wa = $document->getWebAssetManager();

        $wa->addInlineStyle(
            $this->buildPluginCss(),
            ['name' => 'plg_system_sponsoredarticles.inline'],
            [],
            []
        );

        $customCss = $this->buildCustomCss();

        if ($customCss !== '') {
            $wa->addInlineStyle(
                $customCss,
                ['name' => 'plg_system_sponsoredarticles.custom'],
                [],
                ['plg_system_sponsoredarticles.inline']
            );
        }
    }

    /**
     * Modifies rendered HTML to add sponsored-article class to sponsored articles
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     */
    public function onAfterRender(Event $event): void
    {
        $app = $this->getApplication();

        // Only run on site (not admin)
        if ($app->isClient('administrator')) {
            return;
        }

        // Only process HTML documents
        $document = $app->getDocument();
        if ($document->getType() !== 'html') {
            return;
        }

        // Get the rendered body
        $body = $app->getBody();

        // Get the sponsored articles (id => alias) from the database
        $sponsoredArticles = $this->getSponsoredArticles();

        if (empty($sponsoredArticles)) {
            // Nothing to mark (the CSS is added in onBeforeCompileHead)
            return;
        }

        // Get container class patterns for detection
        $containerClasses = $this->getContainerClasses();

        // Build a combined pattern to find all container opening tags
        $classPatterns = array_map(function($class) {
            return preg_quote($class, '/');
        }, $containerClasses);
        $combinedClassPattern = implode('|', $classPatterns);
        $containerPattern = '/<(div|article|tr)[^>]*class="[^"]*(?:' . $combinedClassPattern . ')[^"]*"[^>]*>/i';

        // Find all container opening tags and their positions
        preg_match_all($containerPattern, $body, $containerMatches, PREG_OFFSET_CAPTURE);

        if (empty($containerMatches[0])) {
            // No containers found, nothing to mark
            return;
        }

        // Build list of container boundaries (start position -> next container start or end of body)
        // This helps us determine which container actually contains a given link
        $containerBoundaries = [];
        $containerCount = count($containerMatches[0]);

        for ($i = 0; $i < $containerCount; $i++) {
            $containerTag = $containerMatches[0][$i][0];
            $containerPosition = $containerMatches[0][$i][1];

            // The container ends where the next container begins (or at end of body), but never
            // later than its own closing tag, so links in the sidebar or footer after the last
            // container are not mistaken for part of it
            $nextContainerStart = ($i + 1 < $containerCount)
                ? $containerMatches[0][$i + 1][1]
                : strlen($body);

            $elementEnd = $this->findElementEnd(
                $body,
                strtolower($containerMatches[1][$i][0]),
                $containerPosition + strlen($containerTag)
            );

            if ($elementEnd !== null) {
                $nextContainerStart = min($nextContainerStart, $elementEnd);
            }

            $containerBoundaries[] = [
                'tag' => $containerTag,
                'position' => $containerPosition,
                'endsBefore' => $nextContainerStart
            ];
        }

        // Build list of replacements to make (tag position => container data)
        // We collect all replacements first, then apply them from end to start
        // to avoid position shifting issues
        $replacements = [];

        // Look at every link on the page once. A link counts only if it points at one of the
        // sponsored articles (by real article ID or alias) AND it is the article's own title or
        // read-more link: either it carries itemprop="url" (older templates), or it sits directly
        // inside a heading, a list-title cell or a read-more paragraph (stock Joomla layouts).
        // That keeps sidebar, related-article, tag-cloud and "category" links out.
        if (preg_match_all('/<a\s[^>]*>/i', $body, $linkMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($linkMatches[0] as $linkMatch) {
                $linkTag      = $linkMatch[0];
                $linkPosition = $linkMatch[1];

                if (!preg_match('/\bhref\s*=\s*"([^"]*)"/i', $linkTag, $hrefMatch)) {
                    continue;
                }

                if (!$this->isSponsoredArticleUrl($hrefMatch[1], $sponsoredArticles)) {
                    continue;
                }

                if (!$this->isArticleOwnLink($body, $linkTag, $linkPosition)) {
                    continue;
                }

                // Find the container that actually contains this link
                // The link must be between container start and the next container's start
                foreach ($containerBoundaries as $container) {
                    if ($linkPosition > $container['position'] && $linkPosition < $container['endsBefore']) {
                        // This container actually contains the link
                        if (strpos($container['tag'], 'cs-sponsored-item') === false) {
                            // Mark this container for replacement (avoid duplicates by using position as key)
                            $replacements[$container['position']] = $container;
                        }
                        break; // Found the containing container, stop looking
                    }
                }
            }
        }

        // Sort replacements by position descending (process from end to start)
        krsort($replacements);

        // Apply all replacements
        foreach ($replacements as $position => $container) {
            $openingTag = $container['tag'];
            $tagPosition = $container['position'];

            // Add our class to the opening tag
            $newOpeningTag = preg_replace(
                '/(<(?:div|article|tr)[^>]*class=")([^"]*")/i',
                '$1cs-sponsored-item $2',
                $openingTag,
                1
            );

            // Replace only this specific occurrence
            $body = substr($body, 0, $tagPosition) . $newOpeningTag . substr($body, $tagPosition + strlen($openingTag));
        }

        $app->setBody($body);
    }

    /**
     * Build the plugin's own CSS from the Styling options.
     *
     * With every optional setting empty this is the CSS the plugin has always output.
     * Every value is validated before it is printed, and '<' is escaped in the final string.
     *
     * @return  string
     */
    private function buildPluginCss(): string
    {
        $background = $this->sanitizeColor((string) $this->params->get('background_color', ''), self::DEFAULT_BACKGROUND);

        $css  = "/* CS Sponsored Articles Plugin */\n";
        $css .= ".cs-sponsored-item { background-color: {$background} !important; }\n";

        // Optional quick styling options (empty = not output, so nothing changes on upgrade)
        $declarations = [];

        $textColor = $this->sanitizeColor((string) $this->params->get('text_color', ''), '');

        if ($textColor !== '') {
            $declarations[] = 'color: ' . $textColor;
        }

        $borderColor = $this->sanitizeColor((string) $this->params->get('border_color', ''), '');
        $borderWidth = $this->sanitizeLength($this->params->get('border_width', ''), 20);

        if ($borderColor !== '' || $borderWidth !== '') {
            $declarations[] = 'border-style: solid';
            $declarations[] = 'border-width: ' . ($borderWidth !== '' ? $borderWidth : '1px');

            if ($borderColor !== '') {
                $declarations[] = 'border-color: ' . $borderColor;
            }
        }

        $borderRadius = $this->sanitizeLength($this->params->get('border_radius', ''), 100);

        if ($borderRadius !== '') {
            $declarations[] = 'border-radius: ' . $borderRadius;
        }

        $padding = $this->sanitizeLength($this->params->get('padding', ''), 100);

        if ($padding !== '') {
            $declarations[] = 'padding: ' . $padding;
        }

        if ($declarations) {
            $css .= '.cs-sponsored-item { ' . implode('; ', $declarations) . "; }\n";
        }

        // Optional dark-mode colours (empty = not output)
        $darkDeclarations = [];

        $darkBackground = $this->sanitizeColor((string) $this->params->get('dark_background_color', ''), '');

        if ($darkBackground !== '') {
            $darkDeclarations[] = 'background-color: ' . $darkBackground . ' !important';
        }

        $darkText = $this->sanitizeColor((string) $this->params->get('dark_text_color', ''), '');

        if ($darkText !== '') {
            $darkDeclarations[] = 'color: ' . $darkText;
        }

        if ($darkDeclarations) {
            $rule  = '{ ' . implode('; ', $darkDeclarations) . ' }';
            $css  .= '@media (prefers-color-scheme: dark) { :root:not([data-bs-theme="light"]) .cs-sponsored-item ' . $rule . " }\n";
            $css  .= '[data-bs-theme="dark"] .cs-sponsored-item ' . $rule . "\n";
        }

        // Hide the custom field value display (Yes/No) from frontend
        $css .= '.field-entry.sponsored-article { display: none !important; }';

        return $this->escapeCss($css);
    }

    /**
     * Build the admin's Custom CSS, escaped for output inside a style element.
     *
     * @return  string
     */
    private function buildCustomCss(): string
    {
        $css = trim((string) $this->params->get('custom_css', ''));

        return $css === '' ? '' : $this->escapeCss($css);
    }

    /**
     * Escape CSS for output inside a style element. Every way out of the element starts with '<',
     * so escaping that one character is enough (and keeps '>' usable as the child combinator).
     *
     * @param   string  $css  The CSS
     *
     * @return  string
     */
    private function escapeCss(string $css): string
    {
        return str_replace('<', '\00003c ', $css);
    }

    /**
     * Accept only a strict set of CSS colour formats: hex, rgb()/rgba(), hsl()/hsla() or a colour keyword.
     *
     * @param   string  $value     The raw value from the plugin parameters
     * @param   string  $fallback  Returned when the value is empty or not a valid colour
     *
     * @return  string
     */
    private function sanitizeColor(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        $valid = preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)
            || preg_match('/^(?:rgb|hsl)a?\(\s*[0-9.]+%?(?:deg)?(?:\s*,\s*|\s+)[0-9.]+%?(?:\s*,\s*|\s+)[0-9.]+%?(?:\s*[,\/]\s*[0-9.]+%?)?\s*\)$/i', $value)
            || preg_match('/^[a-z]{3,30}$/i', $value);

        return $valid ? $value : $fallback;
    }

    /**
     * Turn a numeric setting into a pixel length, or '' when empty or invalid.
     *
     * @param   mixed  $value  The raw value from the plugin parameters
     * @param   int    $max    The largest accepted value
     *
     * @return  string
     */
    private function sanitizeLength($value, int $max): string
    {
        $value = trim((string) $value);

        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $number = (float) $value;

        if ($number < 0 || $number > $max) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.') . 'px';
    }

    /**
     * Get container CSS classes based on template selection
     *
     * @return  array  Array of CSS class names to look for as blog item containers
     */
    private function getContainerClasses(): array
    {
        $templateType = $this->params->get('template_type', 'auto');

        // Template-specific container classes
        $templateClasses = [
            'cassiopeia' => ['com-content-category-blog__item', 'blog-item', 'cat-list-row'],
            'tck' => ['tck-article'],
            'yootheme' => ['uk-article'],
            'ja' => ['ja-blog-item', 'ja-item'],
        ];

        // If a specific template is selected, return only those classes
        if ($templateType !== 'auto' && $templateType !== 'custom' && isset($templateClasses[$templateType])) {
            return $templateClasses[$templateType];
        }

        // Handle custom class
        if ($templateType === 'custom') {
            $customClass = trim($this->params->get('custom_container_class', ''));
            if (!empty($customClass)) {
                return [$customClass];
            }
        }

        // Auto-detect: return all container classes in priority order
        return [
            'tck-article',                      // Template Creator CK
            'com-content-category-blog__item',  // Cassiopeia / Joomla default
            'uk-article',                       // YooTheme
            'ja-blog-item',                     // JoomlArt
            'ja-item',                          // JoomlArt alt
            'blog-item',                        // Generic
            'news-item',                        // Generic
            'article-item',                     // Generic
            'cat-list-row',                     // Joomla category list (table rows)
        ];
    }

    /**
     * Get the sponsored articles from the database using the custom field
     *
     * @return  array  Article alias, keyed by article ID
     */
    private function getSponsoredArticles(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Query to get articles that have the sponsored-article custom field set to 1
        $query = $db->getQuery(true)
            ->select([$db->quoteName('c.id'), $db->quoteName('c.alias')])
            ->from($db->quoteName('#__content', 'c'))
            ->join('INNER', $db->quoteName('#__fields_values', 'fv') . ' ON ' . $db->quoteName('fv.item_id') . ' = ' . $db->quoteName('c.id'))
            ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('fv.field_id'))
            ->where($db->quoteName('c.state') . ' = 1')
            ->where($db->quoteName('f.name') . ' = ' . $db->quote('sponsored-article'))
            ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('fv.value') . ' = ' . $db->quote('1'));

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $articles = [];

        foreach ($rows as $row) {
            $articles[(int) $row->id] = strtolower((string) $row->alias);
        }

        return $articles;
    }

    /**
     * Does this link URL point at one of the sponsored articles?
     *
     * Understands the plain (non-SEF) article URL with its ID, and SEF URLs ending in
     * "alias", "alias.html", "123-alias" or "123-alias.html". A leading number must be the
     * real ID of a sponsored article with that alias, and the alias must match the whole
     * last segment, so "news" never matches "/news-archive".
     *
     * @param   string  $href              The href attribute (HTML-escaped as in the page)
     * @param   array   $sponsoredArticles  Alias keyed by article ID
     *
     * @return  boolean
     */
    private function isSponsoredArticleUrl(string $href, array $sponsoredArticles): bool
    {
        $parts = parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));

        if (!\is_array($parts)) {
            return false;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        // Non-SEF: index.php?option=com_content&view=article&id=123:alias
        if (($query['option'] ?? '') === 'com_content' && ($query['view'] ?? '') === 'article' && isset($query['id'])) {
            return isset($sponsoredArticles[(int) $query['id']]);
        }

        // SEF: look at the last path segment
        $path = rtrim($parts['path'] ?? '', '/');

        if ($path === '') {
            return false;
        }

        $segment = strtolower(rawurldecode(basename($path)));
        $segment = preg_replace('/\.html?$/', '', $segment);

        if (preg_match('/^(\d+)-(.+)$/', $segment, $match) && ($sponsoredArticles[(int) $match[1]] ?? null) === $match[2]) {
            return true;
        }

        return \in_array($segment, $sponsoredArticles, true);
    }

    /**
     * Is this link the article's own title or read-more link, rather than a sidebar,
     * related-article or tag-cloud link that happens to point at the same article?
     *
     * @param   string   $body      The page HTML
     * @param   string   $linkTag   The opening a tag
     * @param   integer  $position  Where the tag starts in the page
     *
     * @return  boolean
     */
    private function isArticleOwnLink(string $body, string $linkTag, int $position): bool
    {
        // Older templates mark the title link with itemprop="url"
        if (preg_match('/\bitemprop\s*=\s*"url"/i', $linkTag)) {
            return true;
        }

        // Read-more button
        if (preg_match('/\bclass\s*=\s*"[^"]*\breadmore\b/i', $linkTag)) {
            return true;
        }

        // What comes just before the link: a heading, a list-title cell or a read-more paragraph
        $before = substr($body, max(0, $position - 300), min(300, $position));
        $wrap   = '(?:<(?:span|strong)\b[^>]*>\s*)*';

        return (bool) preg_match(
            '/(?:<h[1-6]\b[^>]*>|<(?:th|td)\b[^>]*\blist-title\b[^>]*>|<p\b[^>]*\breadmore\b[^>]*>)\s*' . $wrap . '$/i',
            $before
        );
    }

    /**
     * Find where an element's closing tag starts, counting nested tags of the same name.
     *
     * @param   string   $body      The page HTML
     * @param   string   $tagName   The element name (div, article, tr)
     * @param   integer  $offset    Position just after the element's opening tag
     *
     * @return  integer|null  Position of the closing tag, or null when the markup is unbalanced
     */
    private function findElementEnd(string $body, string $tagName, int $offset): ?int
    {
        $pattern = '/<(\/?)' . preg_quote($tagName, '/') . '\b[^>]*>/i';
        $depth   = 1;
        $cursor  = $offset;

        while (preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE, $cursor)) {
            $depth += $match[1][0] === '/' ? -1 : 1;

            if ($depth === 0) {
                return $match[0][1];
            }

            $cursor = $match[0][1] + strlen($match[0][0]);
        }

        return null;
    }
}
