<?php
/**
 * @package     CyberSalt\Plugin\System\SponsoredArticles
 * @copyright   Copyright (C) 2024 CyberSalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace CyberSalt\Plugin\System\SponsoredArticles\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;

/**
 * CS Sponsored Articles Plugin
 *
 * Automatically detects articles with Link C Text set to 'sponsored1' and
 * adds a 'sponsored-article' CSS class to style them differently in blog views.
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
            'onBeforeCompileHead' => 'onBeforeCompileHead',
        ];
    }

    /**
     * Adds JavaScript to detect and style sponsored articles
     *
     * @param   BeforeCompileHeadEvent  $event  The event object
     *
     * @return  void
     */
    public function onBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $app = $this->getApplication();

        // Only run on site (not admin)
        if ($app->isClient('administrator')) {
            return;
        }

        // Get database
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'urls']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $articles = $db->loadObjectList();

        $sponsoredIds = [];

        // Check each article for sponsored status
        foreach ($articles as $article) {
            if ($article->urls) {
                $urlsData = json_decode($article->urls);
                if (isset($urlsData->urlltext) && trim($urlsData->urlltext) === 'sponsored1') {
                    $sponsoredIds[] = (int)$article->id;
                }
            }
        }

        // Only output JavaScript if we have sponsored articles
        if (!empty($sponsoredIds)) {
            $sponsoredIdsJson = json_encode($sponsoredIds);

            $script = "
            document.addEventListener('DOMContentLoaded', function() {
                var sponsoredIds = {$sponsoredIdsJson};
                var blogItems = document.querySelectorAll('.blog-item');

                blogItems.forEach(function(item) {
                    var link = item.querySelector('h2 a[href]');
                    if (link) {
                        var href = link.getAttribute('href');
                        var match = href.match(/\\/(\\d+)-/);
                        if (match) {
                            var articleId = parseInt(match[1]);
                            if (sponsoredIds.indexOf(articleId) !== -1) {
                                item.classList.add('sponsored-article');
                            }
                        }
                    }
                });
            });
            ";

            $app->getDocument()->addScriptDeclaration($script);
        }
    }
}
