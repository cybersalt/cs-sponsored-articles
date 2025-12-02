<?php
/**
 * @package     CyberSalt\Plugin\System\SponsoredArticles
 * @copyright   Copyright (C) 2025 CyberSalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace CyberSalt\Plugin\System\SponsoredArticles\Extension;

defined('_JEXEC') or die;

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
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRender' => 'onAfterRender',
        ];
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

        // Get sponsored article aliases from database
        $sponsoredAliases = $this->getSponsoredArticleAliases();

        if (empty($sponsoredAliases)) {
            // Still inject CSS to hide the custom field display
            $backgroundColor = $this->params->get('background_color', '#fff3cd');
            $css = "/* CS Sponsored Articles Plugin */\n";
            $css .= ".cs-sponsored-item { background-color: {$backgroundColor} !important; }\n";
            $css .= ".field-entry.sponsored-article { display: none !important; }";
            $body = str_replace('</head>', '<style>' . $css . '</style></head>', $body);
            $app->setBody($body);
            return;
        }

        // Get container class patterns for detection
        $containerClasses = $this->getContainerClasses();

        // Build a combined pattern to find all container opening tags
        $classPatterns = array_map(function($class) {
            return preg_quote($class, '/');
        }, $containerClasses);
        $combinedClassPattern = implode('|', $classPatterns);
        $containerPattern = '/<(div|article)[^>]*class="[^"]*(?:' . $combinedClassPattern . ')[^"]*"[^>]*>/i';

        // Find all container opening tags and their positions
        preg_match_all($containerPattern, $body, $containerMatches, PREG_OFFSET_CAPTURE);

        if (empty($containerMatches[0])) {
            // No containers found, just inject CSS and return
            $backgroundColor = $this->params->get('background_color', '#fff3cd');
            $css = "/* CS Sponsored Articles Plugin */\n";
            $css .= ".cs-sponsored-item { background-color: {$backgroundColor} !important; }\n";
            $css .= ".field-entry.sponsored-article { display: none !important; }";
            $body = str_replace('</head>', '<style>' . $css . '</style></head>', $body);
            $app->setBody($body);
            return;
        }

        // Build list of container boundaries (start position -> next container start or end of body)
        // This helps us determine which container actually contains a given link
        $containerBoundaries = [];
        $containerCount = count($containerMatches[0]);

        for ($i = 0; $i < $containerCount; $i++) {
            $containerTag = $containerMatches[0][$i][0];
            $containerPosition = $containerMatches[0][$i][1];

            // The container ends where the next container begins (or at end of body)
            $nextContainerStart = ($i + 1 < $containerCount)
                ? $containerMatches[0][$i + 1][1]
                : strlen($body);

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

        foreach ($sponsoredAliases as $alias) {
            $quotedAlias = preg_quote($alias, '/');

            // Pattern to find article title links - matches href containing the alias
            // We look specifically for links with itemprop="url" which identifies the main article link
            // This avoids matching sidebar links, related articles, tag clouds, or other secondary links
            // Handles: /alias.html, /alias, /123-alias.html (ID-prefixed), /alias?param, /alias#anchor
            // The itemprop="url" can appear before or after the href
            $linkPattern = '/<a[^>]*(?:itemprop="url"[^>]*href="[^"]*\/(?:\d+-)?' . $quotedAlias . '(?:\.html)?[^"]*"|href="[^"]*\/(?:\d+-)?' . $quotedAlias . '(?:\.html)?[^"]*"[^>]*itemprop="url")[^>]*>/i';

            // Find ALL occurrences of this article link (in case it appears multiple times)
            if (!preg_match_all($linkPattern, $body, $linkMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // For each link occurrence, find its container
            foreach ($linkMatches[0] as $linkMatch) {
                $linkPosition = $linkMatch[1];

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
                '/(<(?:div|article)[^>]*class=")([^"]*")/i',
                '$1cs-sponsored-item $2',
                $openingTag,
                1
            );

            // Replace only this specific occurrence
            $body = substr($body, 0, $tagPosition) . $newOpeningTag . substr($body, $tagPosition + strlen($openingTag));
        }

        // Inject CSS
        $backgroundColor = $this->params->get('background_color', '#fff3cd');
        $css = "/* CS Sponsored Articles Plugin */\n";
        $css .= ".cs-sponsored-item { background-color: {$backgroundColor} !important; }\n";
        // Hide the custom field value display (Yes/No) from frontend
        $css .= ".field-entry.sponsored-article { display: none !important; }";

        // Insert CSS before </head>
        $body = str_replace('</head>', '<style>' . $css . '</style></head>', $body);

        $app->setBody($body);
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
            'cassiopeia' => ['com-content-category-blog__item'],
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
        ];
    }

    /**
     * Get aliases of sponsored articles from database using custom field
     *
     * @return  array  Array of article aliases
     */
    private function getSponsoredArticleAliases(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Query to get articles that have the sponsored-article custom field set to 1
        $query = $db->getQuery(true)
            ->select($db->quoteName('c.alias'))
            ->from($db->quoteName('#__content', 'c'))
            ->join('INNER', $db->quoteName('#__fields_values', 'fv') . ' ON ' . $db->quoteName('fv.item_id') . ' = ' . $db->quoteName('c.id'))
            ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('fv.field_id'))
            ->where($db->quoteName('c.state') . ' = 1')
            ->where($db->quoteName('f.name') . ' = ' . $db->quote('sponsored-article'))
            ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('fv.value') . ' = ' . $db->quote('1'));

        $db->setQuery($query);
        $results = $db->loadColumn();

        return $results ?: [];
    }
}
