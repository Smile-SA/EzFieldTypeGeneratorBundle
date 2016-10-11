# SmileEzFieldTypeGeneratorBundle

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/b3954da8-43b5-4394-a233-28113d57676c/mini.png)](https://insight.sensiolabs.com/projects/b3954da8-43b5-4394-a233-28113d57676c)

This bundle aims to provide generate:fieldtype command to generate
eZ Platform FieldType bundle structure.

## Installation

### Get the bundle using composer

Add SmileEzFieldTypeGeneratorBundle by running this command from the terminal at the root of
your eZPlatform project:

```bash
composer require smile/ez-fieldtypegenerator-bundle
```


### Enable the bundle

To start using the bundle, register the bundle in your application's kernel class:

```php
// ezpublish/EzPublishKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Smile\EzFieldTypeGeneratorBundle\SmileEzFieldTypeGeneratorBundle(),
        // ...
    );
}
```


### How to use the new command

```bash
php app/console generate:fieldtype

php app/console assets:install --symlink web

php app/console assetic:dump
```



As generate:bundle, you should enter a valid namepsace.

New required entry is le FieldType name.

A bundle is generated automatically with all code structure to manage new eZ Platform field type
