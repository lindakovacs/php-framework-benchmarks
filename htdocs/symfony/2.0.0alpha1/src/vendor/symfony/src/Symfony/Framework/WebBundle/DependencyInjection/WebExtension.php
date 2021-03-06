<?php

namespace Symfony\Framework\WebBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Reference;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebExtension extends LoaderExtension
{
  protected $resources = array(
    'templating' => 'templating.xml',
    'web'        => 'web.xml',
    'debug'      => 'debug.xml',
  );

  public function webLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
    $configuration->merge($loader->load($this->resources['web']));

    return $configuration;
  }

  /**
   * Loads the templating configuration.
   *
   * @param array $config A configuration array
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function templatingLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
    $configuration->merge($loader->load($this->resources['templating']));

    $configuration->setParameter('templating.output_escaper', array_key_exists('escaping', $config) ? $config['escaping'] : false);
    $configuration->setParameter('templating.assets.version', array_key_exists('assets_version', $config) ? $config['assets_version'] : null);

    // path for the filesystem loader
    if (isset($config['path']))
    {
      $configuration->setParameter('templating.loader.filesystem.path', $config['path']);
    }

    // loaders
    if (isset($config['loader']))
    {
      $loaders = array();
      $ids = is_array($config['loader']) ? $config['loader'] : array($config['loader']);
      foreach ($ids as $id)
      {
        $loaders[] = new Reference($id);
      }
    }
    else
    {
      $loaders = array(
        new Reference('templating.loader.filesystem'),
      );
    }

    if (1 === count($loaders))
    {
      $configuration->setAlias('templating.loader', (string) $loaders[0]);
    }
    else
    {
      $configuration->getDefinition('templating.loader.chain')->addArgument($loaders);
      $configuration->setAlias('templating.loader', 'templating.loader.chain');
    }

    // cache?
    if (isset($config['cache']))
    {
      // wrap the loader with some cache
      $configuration->setDefinition('templating.loader.wrapped', $configuration->findDefinition('templating.loader'));
      $configuration->setDefinition('templating.loader', $configuration->getDefinition('templating.loader.cache'));
      $configuration->setParameter('templating.loader.cache.path', $config['cache']);
    }

    return $configuration;
  }

  public function debugLoad($config)
  {
    $configuration = new BuilderConfiguration();

    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
    $configuration->merge($loader->load($this->resources['debug']));

    if (isset($config['exception']) && $config['exception'])
    {
      $configuration->merge($loader->load('debug_exception_handler.xml'));
    }

    if (isset($config['toolbar']) && $config['toolbar'])
    {
      $configuration->merge($loader->load('debug_data_collector.xml'));
      $configuration->merge($loader->load('debug_web_debug_toolbar.xml'));
    }

    if (isset($config['ide']) && 'textmate' === $config['ide'])
    {
      $configuration->setParameter('web_debug.file_link_format', 'txmt://open?url=file://%%f&line=%%l');
    }

    $configuration->setAlias('event_dispatcher', 'debug.event_dispatcher');

    return $configuration;
  }

  public function getNamespace()
  {
    return 'http://www.symfony-project.org/schema/dic/symfony/web';
  }

  public function getAlias()
  {
    return 'web';
  }
}
