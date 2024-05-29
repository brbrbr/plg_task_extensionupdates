<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\ExtensionUpdates\Field;

use Joomla\CMS\Form\Field\UserField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Field to select a user ID from a modal list.
 *
 * @since  1.6
 */
class SuperuserField extends UserField
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
        return [8];
        if (isset($this->element['groups'])) {
            return explode(',', $this->element['groups']);
        }

        return [8];
    }

 

}
