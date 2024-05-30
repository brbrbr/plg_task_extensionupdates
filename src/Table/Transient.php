<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\ExtensionUpdates\Table;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\CMS\Table\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content History table.
 *
 * @since  3.2
 */
class Transient extends Table
{
    public $character_count = 0;
    public $version_data = [];
    public $version_note = '';
    public $sha1_hash = Null;
    public $save_date = "2000-01-01 01:01:01";

    /**
     * Constructor
     *
     * @param   DatabaseDriver        $db          Database connector object
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table
     *
     * @since   3.1
     */
    public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__history', 'version_id', $db, $dispatcher);
    }



    /**
     * Method to save a version snapshot to the content history table.
     *
     * @param   string   $typeAlias  Typealias of the content type
     * @param   integer  $id         ID of the content item
     * @param   mixed    $data       Array or object of data that can be
     *                               en- and decoded into JSON
     * @param   string   $note       Note for the version to store
     *
     * @return  boolean  True on success, otherwise false.
     *
     * @since   4.0.0
     */
    public  function storeTransient($data, $note = 'transient')
    {
        $this->version_data = json_encode($data);
        $this->version_note = $note;
        $result = $this->store();

        return $result;
    }

    /**
     * Overrides Table::store to set modified hash, user id, and save date.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   3.2
     */
    public function store($updateNulls = false)
    {
        $versionData = $this->version_data ?? '';
        $this->character_count =  \strlen($versionData);

        if (!isset($this->sha1_hash)) {
            $this->sha1_hash = $this->getSha1($this->version_data);
        }
        // Modify author and date only when not toggling Keep Forever
        $this->save_date =  Factory::getDate()->toSql();
        return parent::store($updateNulls);
    }

    /**
     * Utility method to get the hash after removing selected values. This lets us detect changes other than
     * modified date (which will change on every save).
     *
     * @param   object | array        $jsonData   Either an object or a string with json-encoded data
     *
     * @return  string  SHA1 hash on success. Empty string on failure.
     *
     * @since   3.2
     */
    public function getSha1($jsonData)
    {
        $object = \is_object($jsonData) || \is_array($jsonData) ? $jsonData : json_decode($jsonData);
        return sha1(json_encode($object));
    }

    /**
     * Utility method to get a matching row based on the hash value and id columns.
     * This lets us check to make sure we don't save duplicate versions.
     *
     * @return  string  SHA1 hash on success. Empty string on failure.
     *
     * @since   3.2
     */
    public function getHashMatch(string $itemId, string $sha1Hash)
    {
        $db       = $this->_db;
        $query = $db->createQuery();
        $query->select('*')
            ->from($db->quoteName('#__history'))
            ->where($db->quoteName('item_id') . ' = :item_id')
            ->where($db->quoteName('sha1_hash') . ' = :sha1_hash')
            ->bind(':item_id', $itemId, ParameterType::STRING)
            ->bind(':sha1_hash', $sha1Hash)
            ->setLimit(1);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Utility method to remove the oldest versions of an item, saving only the most recent versions.
     *
     * @param   integer  $maxVersions  The maximum number of versions to save. All others will be deleted.
     *
     * @return  boolean   true on success, false on failure.
     *
     * @since   3.2
     */
    public function deleteOldVersions($maxVersions)
    {
        $result = true;
        // Get the list of version_id values we want to save
        $db        = $this->_db;
        $itemId    = $this->item_id;
        $query = $db->createQuery();
        $query->select($db->quoteName('version_id'))
            ->from($db->quoteName('#__history'))
            ->where($db->quoteName('item_id') . ' = :item_id')
            ->where($db->quoteName('keep_forever') . ' != 1')
            ->bind(':item_id', $itemId, ParameterType::STRING)
            ->order($db->quoteName('save_date') . ' DESC ');

        $query->setLimit((int) $maxVersions);
        $db->setQuery($query);
        $idsToSave = $db->loadColumn(0);

        // Don't process delete query unless we have at least the maximum allowed versions
        if (\count($idsToSave) === (int) $maxVersions) {
            // Delete any rows not in our list and and not flagged to keep forever.
            $query = $db->createQuery()
                ->delete($db->quoteName('#__history'))
                ->where($db->quoteName('item_id') . ' = :item_id')
                ->whereNotIn($db->quoteName('version_id'), $idsToSave)
                ->where($db->quoteName('keep_forever') . ' != 1')
                ->bind(':item_id', $itemId, ParameterType::STRING);
            $db->setQuery($query);
            $result = (bool) $db->execute();
        }

        return $result;
    }
}
