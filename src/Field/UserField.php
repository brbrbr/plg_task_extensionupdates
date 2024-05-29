<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\ExtensionUpdates\Field;

use Joomla\CMS\Form\Field\UserField as JoomlaUserField;
use Joomla\CMS\Access\Access;

use Joomla\CMS\Table\Asset;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Field to select a user ID from a modal list.
 *
 * @since  1.6
 */
class UserField extends JoomlaUserField
{

    protected $layout = 'joomla.form.field.user';


    /**
     * Method to get the filtering groups (null means no filtering)
     *
     * @return  string[]  Array of filtering groups or null.
     *
     * @since   1.6
     */
    protected function getGroups()
    {
        $db     = $this->getDatabase();
        $rootId = (new Asset($db))->getRootId();

        $rules     = Access::getAssetRules($rootId)->getData();
        $rawGroups = $rules['core.admin']->getData();
        $groups    = [];

        if (empty($rawGroups)) {
            return $groups;
        }

        foreach ($rawGroups as $g => $enabled) {
            if ($enabled) {
                $groups[] = $g;
            }
        }
        return $groups;
    }
}
