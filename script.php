<?php


/**
 * @since    1.0.3
 * @package    BLC Packge
 * @module     Tasks - Extensionupdates
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright  2024  Bram Brambring
 * @license GNU General Public License version 3 or later;
 */

 // phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR12.Classes.AnonClassDeclaration
return new class() implements ServiceProviderInterface
// phpcs:enable PSR12.Classes.AnonClassDeclaration
{
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            // phpcs:disable PSR12.Classes.AnonClassDeclaration
            new class($container->get(AdministratorApplication::class)) implements InstallerScriptInterface
            // phpcs:enable PSR12.Classes.AnonClassDeclaration
            {
                protected AdministratorApplication $app;
                protected DatabaseDriver $db;

                public function __construct(AdministratorApplication $app)
                {
                    $this->app = $app;
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
					$this->minimumJoomla = '5.1';
					$this->minimumPhp    = '8.1';
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    $query = $this->db->getquery(true);
                    $query->update('`#__extensions`')
                        ->set('`enabled` = 1')
                        ->where('`type` = \'plugin\'')
                        ->where('`folder` = ' . $this->db->quote($adapter->group))
                        ->where('`element` = ' . $this->db->quote($adapter->element));
                    $this->db->setQuery($query)->execute();
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }
				public function preflight($type, $adapter): bool
                {
                    if ($type !== 'uninstall') {
                        // Check for the minimum PHP version before continuing
                        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPhp),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                        // Check for the minimum Joomla version before continuing
                        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                    }
           

                    return true;
                }
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }
            }
        );
    }
};
