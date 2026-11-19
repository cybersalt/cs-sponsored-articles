<?php
/**
 * @package     CyberSalt\Plugin\System\SponsoredArticles
 * @copyright   Copyright (C) 2025 CyberSalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace CyberSalt\Plugin\System\SponsoredArticles\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * Builds the "please contact support" sentence from the plugin's Support settings,
 * so every message the plugin shows carries the right contact details.
 */
class SupportHelper
{
    public const DEFAULT_EMAIL = 'support@cybersalt.com';
    public const DEFAULT_URL   = 'https://www.cybersalt.com/services/support-request-form';
    public const DEFAULT_LABEL = 'Cybersalt support';

    /**
     * Get the contact sentence, for example
     * "Please contact Cybersalt support (support@cybersalt.com / https://...) for assistance."
     *
     * @param   Registry|null  $params  Plugin params; read from the installed plugin when omitted
     *
     * @return  string
     */
    public static function getContactSentence(?Registry $params = null): string
    {
        if ($params === null) {
            $plugin = PluginHelper::getPlugin('system', 'sponsoredarticles');
            $params = new Registry(\is_object($plugin) ? ($plugin->params ?? '') : '');
        }

        $email = trim((string) $params->get('support_email', self::DEFAULT_EMAIL));
        $url   = trim((string) $params->get('support_url', self::DEFAULT_URL));
        $label = trim((string) $params->get('support_label', self::DEFAULT_LABEL));

        if ($label === '') {
            $label = self::DEFAULT_LABEL;
        }

        $details = implode(' / ', array_filter([$email, $url]));

        $key = $details === ''
            ? 'PLG_SYSTEM_SPONSOREDARTICLES_SUPPORT_CONTACT_NO_DETAILS'
            : 'PLG_SYSTEM_SPONSOREDARTICLES_SUPPORT_CONTACT';

        $sentence = $details === '' ? Text::sprintf($key, $label) : Text::sprintf($key, $label, $details);

        // Language file not loaded (for example while installing): fall back to English
        if ($sentence === $key) {
            $sentence = $details === ''
                ? 'Please contact ' . $label . ' for assistance.'
                : 'Please contact ' . $label . ' (' . $details . ') for assistance.';
        }

        return $sentence;
    }
}
