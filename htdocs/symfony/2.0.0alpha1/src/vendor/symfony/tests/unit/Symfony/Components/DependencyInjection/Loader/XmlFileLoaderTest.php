<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;

$t = new LimeTest(40);

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');

require_once $fixturesPath.'/includes/ProjectExtension.php';

class ProjectLoader extends XmlFileLoader
{
  public function parseFile($file)
  {
    return parent::parseFile($file);
  }
}

// ->load()
$t->diag('->load()');

$loader = new ProjectLoader($fixturesPath.'/ini');

try
{
  $loader->load('foo.xml');
  $t->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the loaded file does not exist');
}

// ->parseFile()
$t->diag('->parseFile()');

try
{
  $loader->parseFile($fixturesPath.'/ini/parameters.ini');
  $t->fail('->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
}

$loader = new ProjectLoader($fixturesPath.'/xml');

try
{
  $loader->parseFile($fixturesPath.'/xml/nonvalid.xml');
  $t->fail('->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
}

$xml = $loader->parseFile($fixturesPath.'/xml/services1.xml');
$t->is(get_class($xml), 'Symfony\\Components\\DependencyInjection\\SimpleXMLElement', '->parseFile() returns an SimpleXMLElement object');

// ->load() # parameters
$t->diag('->load() # parameters');
$loader = new ProjectLoader($fixturesPath.'/xml');
$config = $loader->load('services2.xml');
$t->is($config->getParameters(), array('a string', 'foo' => 'bar', 'values' => array(0, 'integer' => 4, 100 => null, 'true', true, false, 'on', 'off', 'float' => 1.3, 1000.3, 'a string', array('foo', 'bar')), 'foo_bar' => new Reference('foo_bar')), '->load() converts XML values to PHP ones');

// ->load() # imports
$t->diag('->load() # imports');
$config = $loader->load('services4.xml');
$t->is($config->getParameters(), array('a string', 'foo' => 'bar', 'bar' => '%foo%', 'values' => array(true, false), 'foo_bar' => new Reference('foo_bar'), 'imported_from_yaml' => true, 'imported_from_ini' => true), '->load() imports and merges imported files');

// ->load() # anonymous services
$t->diag('->load() # anonymous services');
$config = $loader->load('services5.xml');
$services = $config->getDefinitions();
$t->is(count($services), 3, '->load() attributes unique ids to anonymous services');
$args = $services['foo']->getArguments();
$t->is(count($args), 1, '->load() references anonymous services as "normal" ones');
$t->is(get_class($args[0]), 'Symfony\\Components\\DependencyInjection\\Reference', '->load() converts anonymous services to references to "normal" services');
$t->ok(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
$inner = $services[(string) $args[0]];
$t->is($inner->getClass(), 'BarClass', '->load() uses the same configuration as for the anonymous ones');

$args = $inner->getArguments();
$t->is(count($args), 1, '->load() references anonymous services as "normal" ones');
$t->is(get_class($args[0]), 'Symfony\\Components\\DependencyInjection\\Reference', '->load() converts anonymous services to references to "normal" services');
$t->ok(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
$inner = $services[(string) $args[0]];
$t->is($inner->getClass(), 'BazClass', '->load() uses the same configuration as for the anonymous ones');

// ->load() # services
$t->diag('->load() # services');
$config = $loader->load('services6.xml');
$services = $config->getDefinitions();
$t->ok(isset($services['foo']), '->load() parses <service> elements');
$t->is(get_class($services['foo']), 'Symfony\\Components\\DependencyInjection\\Definition', '->load() converts <service> element to Definition instances');
$t->is($services['foo']->getClass(), 'FooClass', '->load() parses the class attribute');
$t->ok($services['shared']->isShared(), '->load() parses the shared attribute');
$t->ok(!$services['non_shared']->isShared(), '->load() parses the shared attribute');
$t->is($services['constructor']->getConstructor(), 'getInstance', '->load() parses the constructor attribute');
$t->is($services['file']->getFile(), '%path%/foo.php', '->load() parses the file tag');
$t->is($services['arguments']->getArguments(), array('foo', new Reference('foo'), array(true, false)), '->load() parses the argument tags');
$t->is($services['configurator1']->getConfigurator(), 'sc_configure', '->load() parses the configurator tag');
$t->is($services['configurator2']->getConfigurator(), array(new Reference('baz'), 'configure'), '->load() parses the configurator tag');
$t->is($services['configurator3']->getConfigurator(), array('BazClass', 'configureStatic'), '->load() parses the configurator tag');
$t->is($services['method_call1']->getMethodCalls(), array(array('setBar', array())), '->load() parses the method_call tag');
$t->is($services['method_call2']->getMethodCalls(), array(array('setBar', array('foo', new Reference('foo'), array(true, false)))), '->load() parses the method_call tag');
$aliases = $config->getAliases();
$t->ok(isset($aliases['alias_for_foo']), '->load() parses <service> elements');
$t->is($aliases['alias_for_foo'], 'foo', '->load() parses aliases');

// ::convertDomElementToArray()
$t->diag('::convertDomElementToArray()');
$doc = new DOMDocument("1.0");
$doc->loadXML('<foo>bar</foo>');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), 'bar', '::convertDomElementToArray() converts a \DomElement to an array');

$doc = new DOMDocument("1.0");
$doc->loadXML('<foo foo="bar" />');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), array('foo' => 'bar'), '::convertDomElementToArray() converts a \DomElement to an array');

$doc = new DOMDocument("1.0");
$doc->loadXML('<foo><foo>bar</foo></foo>');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), array('foo' => 'bar'), '::convertDomElementToArray() converts a \DomElement to an array');

$doc = new DOMDocument("1.0");
$doc->loadXML('<foo><foo>bar<foo>bar</foo></foo></foo>');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), array('foo' => array('value' => 'bar', 'foo' => 'bar')), '::convertDomElementToArray() converts a \DomElement to an array');

$doc = new DOMDocument("1.0");
$doc->loadXML('<foo><foo></foo></foo>');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), array('foo' => null), '::convertDomElementToArray() converts a \DomElement to an array');

$doc = new DOMDocument("1.0");
$doc->loadXML('<foo><foo><!-- foo --></foo></foo>');
$t->is(ProjectLoader::convertDomElementToArray($doc->documentElement), array('foo' => null), '::convertDomElementToArray() converts a \DomElement to an array');

// extensions
$t->diag('extensions');
Loader::registerExtension(new ProjectExtension());
$loader = new ProjectLoader($fixturesPath.'/xml');

$config = $loader->load('services10.xml');
$services = $config->getDefinitions();
$parameters = $config->getParameters();
$t->ok(isset($services['project.service.bar']), '->load() parses extension elements');
$t->ok(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

try
{
  $config = $loader->load('services11.xml');
  $t->fail('->load() throws an InvalidArgumentException if the tag is not valid');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the tag is not valid');
}

try
{
  $config = $loader->load('services12.xml');
  $t->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if an extension is not loaded');
}
