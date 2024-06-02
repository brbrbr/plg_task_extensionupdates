<?php

/**
 * ExtensionUpdates Task Plugin
 *
 * @copyright Copyright (C) 2024 Tobias Zulauf All rights reserved.
 * @copyright Copyright (C) 2024 Bram Brambring All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or later
 */

namespace Joomla\Plugin\Task\ExtensionUpdates\Extension;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use PHPMailer\PHPMailer\Exception as phpMailerException;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Asset;
use Joomla\Utilities\ArrayHelper;
use Joomla\Plugin\Task\ExtensionUpdates\Table\Transient;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Checks for extension Updates and sends an eMail once one has been found
 *
 * @since 1.0.0
 */
final class ExtensionUpdates extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 1.0.0
     */
    private const TASKS_MAP = [
        'update.extensions' => [
            'langConstPrefix' => 'PLG_TASK_EXTENSIONUPDATES_SEND',
            'method'          => 'checkExtensionUpdates',
            'form'            => 'sendForm',
        ],
    ];

    /**
     * @var boolean
     * @since 1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }


    /**
     * Method to get the updates.
     * From update:extensions:check
     *
     * @return array  List of updates
     *
     * @since  1.0.8
     * @throws \Exception
     */


    private function getExtensionsWithUpdate($core = false)
    {
        // Find updates.
        /** @var UpdateModel $model */
        $model = $this->getApplication()->bootComponent('com_installer')
            ->getMVCFactory()->createModel('Update', 'Administrator', ['ignore_request' => true]);

        // Purge the table before checking
        // $model->purge();  
        if ($core) {
            $coreEid = ExtensionHelper::getExtensionRecord('joomla', 'file')->extension_id;
            $model->setState('filter.extension_id', $coreEid);
        } else {
            $model->setState('filter.extension_id', null);
        }

        $model->findUpdates();

        return $model->getItems();
    }

    /**
     * Method to send the update notification.
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  1.0.0
     * @throws \Exception
     */
    private function checkExtensionUpdates(ExecuteTaskEvent $event): int
    {

        $this->logTask('ExtensionUpdates start');

        // Load the parameters.
        $params = $event->getArgument('params');
        $recipients = ArrayHelper::fromObject($params->recipients ?? [], false);
        $sendOnce = (bool)($params->send_once ?? true);
        $specificIds = array_map(function ($item) {
            return $item->user;
        }, $recipients);
        $forcedLanguage = $params->language_override ?? '';
    /*
         * Load the appropriate language. We try to load English (UK), the current user's language and the forced
         * language preference, in this order. This ensures that we'll never end up with untranslated strings in the
         * update email which would make Joomla! seem bad. So, please, if you don't fully understand what the
         * following code does DO NOT TOUCH IT. It makes the difference between a hobbyist CMS and a professional
         * solution!
         */
        $jLanguage = $this->getApplication()->getLanguage();
        $jLanguage->load('lib_joomla', JPATH_ADMINISTRATOR, 'en-GB', true, true);
        $jLanguage->load('lib_joomla', JPATH_ADMINISTRATOR, null, true, true);
        $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, 'en-GB', true, true);
        $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, null, true, false);

        // Then try loading the preferred (forced) language
        if (!empty($forcedLanguage)) {
            $jLanguage->load('lib_joomla', JPATH_ADMINISTRATOR, $forcedLanguage, true, false);
            $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, $forcedLanguage, true, false);
        }


        $extensionUpdates = $this->getExtensionsWithUpdate();
        $coreUpdates = $this->getExtensionsWithUpdate(true);
        $allUpdates = array_merge($coreUpdates, $extensionUpdates);

        if (\count($allUpdates) == 0) {
            $this->logTask('No Updates found');
            return Status::OK;
        }

        $baseURL = Route::link('administrator', 'index.php?option=com_cpanel&view=cpanel&dashboard=system', xhtml: false, absolute: true);

        //TODO
        /**
         * Some third party security solutions require a secret query parameter to allow log in to the administrator
         * backend of the site. The link generated above will be invalid and could probably block the user out of their
         * site, confusing them (they can't understand the third party security solution is not part of Joomla! proper).
         * So, we're calling the onBuildAdministratorLoginURL system plugin event to let these third party solutions
         * add any necessary secret query parameters to the URL. The plugins are supposed to have a method with the
         * signature:
         *
         * public function onBuildAdministratorLoginURL(Uri &$uri);
         *
         * The plugins should modify the $uri object directly and return null.
         */
        //really depricated code in a new plugin
        // $this->getApplication()->triggerEvent('onBuildAdministratorLoginURL', [&$uri]);

        // Let's find out the email addresses to notify
        $superUsers = [];

        if (!empty($specificIds)) {
            $superUsers = $this->getSuperUsers($specificIds);
        }

        if (empty($superUsers)) {
            $superUsers = $this->getSuperUsers();
        }

        if (empty($superUsers)) {
            $this->logTask('No recipients found');
            return Status::KNOCKOUT;
        }


    

        $baseSubstitutions = [
            'sitename'      => $this->getApplication()->get('sitename'),
            'updatelink'    => $baseURL,
        ];


        $body = [$this->replaceTags(Text::plural('PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_HEADER', count($allUpdates)), $baseSubstitutions) . "\n\n"];
        $subject = $this->replaceTags(Text::plural('PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_SUBJECT', count($allUpdates)), $baseSubstitutions);

        foreach ($allUpdates as  $updateValue) {

            // Replace merge codes with their values
            $extensionSubstitutions = [
                'newversion'    => $updateValue->version,
                'curversion'    => $updateValue->current_version,
                'extensiontype' => $updateValue->type,
                'extensionname' => $updateValue->name,
            ];

            $body[] = $this->replaceTags(Text::_('PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_SINGLE'), $extensionSubstitutions) . "\n";
        }

        $body[] = $this->replaceTags(Text::_('PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_FOOTER'), $baseSubstitutions);

        $body = join("\n", $body);

        // Send the emails to the Super Users

        try {

            $mail = clone Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $transientManager = new Transient($this->getDatabase(), $this->getDispatcher());

            $transientData = [
                'body' => $body,
                'subject' => $subject
            ];
            $sha1 = $transientManager->getSha1($transientData);

            $hasRecipient = false;
            foreach ($superUsers as $superUser) {
                $itemId = 'ExtensionUpdates.email.' . $superUser->id;
                if ($sendOnce === false || !$transientManager->getHashMatch($itemId, $sha1)) {
                    $hasRecipient = true;
                    $mail->addBcc($superUser->email, $superUser->name);
                    $transientManager->bind([
                        'sha1_hash' => $sha1,
                        'item_id' => $itemId,
                        'editor_user_id' => $superUser->id
                    ]);
                    $transientManager->storeTransient($transientData, 'transient');
                    $transientManager->deleteOldVersions(1);
                }
            }

            if ($hasRecipient) {
                $mailfrom =   $this->getApplication()->get('mailfrom');
                $fromname = $this->getApplication()->get('fromname');

                if (MailHelper::isEmailAddress($mailfrom)) {
                    $mail->setSender(MailHelper::cleanLine($mailfrom), MailHelper::cleanLine($fromname), false);
                }
                $mail->setBody($body);
                $mail->setSubject($subject);
                $mail->SMTPDebug = false;
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                $mail->isHtml(false);
                $mail->send();
            }
        } catch (MailDisabledException | phpMailerException $exception) {
            try {
                $this->logTask($jLanguage->_($exception->getMessage()));
            } catch (\RuntimeException $exception) {

                return Status::KNOCKOUT;
            }
        }


        $this->logTask('ExtensionUpdates end');

        return Status::OK;
    }

    /**
     * Method to replace tags like in MailTemplate
     *
     * @param   string  $text  The `language string`.
     * @param   array  $tags  key replacment pairs
     *
     * @return string  The text with replaces tags
     *
     * @since  1.0.1
     */

    protected function replaceTags(string $text, array $tags)
    {
        foreach ($tags as $key => $value) {
            // If the value is NULL, replace with an empty string. NULL itself throws notices
            if (\is_null($value)) {
                $value = '';
            }

            if (\is_array($value)) {
                $matches = [];
                $pregKey = preg_quote(strtoupper($key), '/');

                if (preg_match_all('/{' . $pregKey . '}(.*?){\/' . $pregKey . '}/s', $text, $matches)) {
                    foreach ($matches[0] as $i => $match) {
                        $replacement = '';

                        foreach ($value as $name => $subvalue) {
                            if (\is_array($subvalue) && $name == $matches[1][$i]) {
                                $replacement .= implode("\n", $subvalue);
                            } elseif (\is_array($subvalue)) {
                                $replacement .= $this->replaceTags($matches[1][$i], $subvalue);
                            } elseif (\is_string($subvalue) && $name == $matches[1][$i]) {
                                $replacement .= $subvalue;
                            }
                        }

                        $text = str_replace($match, $replacement, $text);
                    }
                }
            } else {
                $text = str_replace('{' . strtoupper($key) . '}', $value, $text);
            }
        }

        return $text;
    }


    /**
     * Returns the Super Users email information. If you provide a comma separated $email list
     * we will check that these emails do belong to Super Users
     * this version overrides the sendemail parameter in the user settings
     *
     * @param   null|array  $userIds  A list of Super Users to email
     *
     * @return  array  The list of Super User emails
     *
     * @since   1.0.1
     */
    private function getSuperUsers(?array $userIds = null)
    {
        $db     = $this->getDatabase();

        // Get a list of groups which have Super User privileges
        $ret = [];

        try {
            $rootId = (new Asset($db))->getRootId();
            $rules     = Access::getAssetRules($rootId)->getData();
            $rawGroups = $rules['core.admin']->getData();
            $groups    = [];

            if (empty($rawGroups)) {
                return $ret;
            }

            foreach ($rawGroups as $g => $enabled) {
                if ($enabled) {
                    $groups[] = $g;
                }
            }

            if (empty($groups)) {
                return $ret;
            }
        } catch (\Exception $exc) {
            return $ret;
        }


        // Get the user information for the Super Administrator users
        try {
            $query = $db->createQuery()
                ->select($db->quoteName(['id', 'name', 'email']))
                ->from($db->quoteName('#__users', 'u'))
                ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), '`u`.`id` = `m`.`user_id`')
                ->whereIn($db->quoteName('m.group_id'), $groups, ParameterType::INTEGER)
                ->where($db->quoteName('block') . ' = 0');

            if (!empty($userIds)) {
                $query->whereIn($db->quoteName('id'), $userIds, ParameterType::INTEGER);
            } else {
                $query->where($db->quoteName('sendEmail') . ' = 1');
            }

            $db->setQuery($query);
            $ret = $db->loadObjectList();
        } catch (\Exception $exc) {
            return $ret;
        }

        return $ret;
    }
}
