<?php

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Ramblers\Plugin\User\Ra_profiles\Extension\Ra_profiles;

// Some Joomla 6 installations do not refresh the plugin namespace map until
// the cache is rebuilt.  Load the extension explicitly as a safe fallback;
// require_once remains harmless when the namespace autoloader has already
// loaded it.
$extensionFile = dirname(__DIR__) . '/src/Extension/Ra_profiles.php';

if (is_file($extensionFile)) {
    require_once $extensionFile;
}

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new Ra_profiles(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('user', 'ra_profiles')
            );
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
