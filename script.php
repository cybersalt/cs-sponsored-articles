<?php
/**
 * @package     CyberSalt\Plugin\System\SponsoredArticles
 * @copyright   Copyright (C) 2025 CyberSalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;

class PlgSystemSponsoredarticlesInstallerScript
{
    /**
     * Runs after install or update
     *
     * @param   string            $type    Type of action (install, update, uninstall)
     * @param   InstallerAdapter  $parent  Parent installer
     *
     * @return  boolean
     */
    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if ($type === 'install' || $type === 'update') {
            try {
                $this->createCustomField();
            } catch (\Exception $e) {
                Log::add('Sponsored Articles: Failed to create custom field - ' . $e->getMessage(), Log::WARNING, 'jerror');
            }
        }

        return true;
    }

    /**
     * Create the Sponsored field group and custom field if they don't exist
     *
     * @return  void
     */
    private function createCustomField(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Check if field group already exists
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__fields_groups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('Sponsored?'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

        $db->setQuery($query);
        $groupId = $db->loadResult();

        // Create field group if it doesn't exist using Table class
        if (!$groupId) {
            $app = Factory::getApplication();
            $mvcFactory = $app->bootComponent('com_fields')->getMVCFactory();
            $groupTable = $mvcFactory->createTable('Group', 'Administrator');

            $now = Factory::getDate()->toSql();
            $userId = $app->getIdentity()->id ?? 0;

            $groupData = [
                'title'       => 'Sponsored?',
                'context'     => 'com_content.article',
                'state'       => 1,
                'language'    => '*',
                'access'      => 1,
                'ordering'    => 0,
                'note'        => '',
                'description' => 'Fields for marking articles as sponsored content.',
                'params'      => '{}',
                'created'     => $now,
                'created_by'  => $userId,
                'modified'    => $now,
                'modified_by' => $userId,
            ];

            if (!$groupTable->bind($groupData)) {
                throw new \Exception('Group bind failed: ' . $groupTable->getError());
            }
            if (!$groupTable->store()) {
                throw new \Exception('Group store failed: ' . $groupTable->getError());
            }
            $groupId = $groupTable->id;
        }

        // Check if field already exists
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('sponsored-article'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

        $db->setQuery($query);
        $fieldId = $db->loadResult();

        // Create field if it doesn't exist using Table class
        if (!$fieldId) {
            $app = Factory::getApplication();
            $mvcFactory = $app->bootComponent('com_fields')->getMVCFactory();
            $fieldTable = $mvcFactory->createTable('Field', 'Administrator');

            if (!$fieldTable) {
                throw new \Exception('Could not create Field table');
            }

            $now = Factory::getDate()->toSql();
            $userId = $app->getIdentity()->id ?? 0;

            $fieldparams = [
                'options' => [
                    'options0' => ['name' => 'No', 'value' => '0'],
                    'options1' => ['name' => 'Yes', 'value' => '1'],
                ],
                'class' => 'btn-group btn-group-yesno',
                'layout' => 'joomla.form.field.radio.switcher',
            ];

            // Display params - hide label and value on frontend
            $params = [
                'hint'               => '',
                'render_class'       => '',
                'class'              => '',
                'showlabel'          => '0',  // Hide label
                'label_render_class' => '',
                'show_on'            => '',   // Empty = show nowhere (not in article view)
                'display'            => '0',  // 0 = Do not automatically display
                'display_readonly'   => '2',
            ];

            $fieldData = [
                'id'            => 0,
                'asset_id'      => 0,
                'title'         => 'Sponsored?',
                'name'          => 'sponsored-article',
                'label'         => 'Sponsored?',
                'type'          => 'radio',
                'context'       => 'com_content.article',
                'group_id'      => (int) $groupId,
                'state'         => 1,
                'required'      => 0,
                'only_use_in_subform' => 0,
                'default_value' => '0',
                'language'      => '*',
                'access'        => 1,
                'ordering'      => 0,
                'note'          => '',
                'description'   => 'Mark this article as sponsored to highlight it in blog views.',
                'params'        => json_encode($params),
                'fieldparams'   => json_encode($fieldparams),
                'created'       => $now,
                'created_by'    => $userId,
                'modified'      => $now,
                'modified_by'   => $userId,
            ];

            if (!$fieldTable->bind($fieldData)) {
                throw new \Exception('Field bind failed: ' . $fieldTable->getError());
            }

            if (!$fieldTable->check()) {
                throw new \Exception('Field check failed: ' . $fieldTable->getError());
            }

            if (!$fieldTable->store()) {
                throw new \Exception('Field store failed: ' . $fieldTable->getError());
            }
        }
    }
}
