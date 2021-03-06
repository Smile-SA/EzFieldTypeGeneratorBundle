<?php

namespace Smile\EzFieldTypeGeneratorBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Kernel;

class FieldTypeGenerator extends Generator
{
    /**
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * FieldTypeGenerator constructor.
     *
     * @param Kernel     $kernel
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function generate($namespace, $bundle, $dir, $fieldTypeName, $yuiFieldTypeNamespace)
    {
        $dir .= '/'.strtr($namespace, '\\', '/');
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" exists but is a file.',
                        realpath($dir)
                    )
                );
            }

            $files = scandir($dir);
            if ($files != array('.', '..')) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" is not empty.',
                        realpath($dir)
                    )
                );
            }

            if (!is_writable($dir)) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" is not writable.',
                        realpath($dir)
                    )
                );
            }
        }

        $basename = substr($bundle, 0, -6);
        $parameters = array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
            'bundle_basename' => $basename,
            'bundle_basename_lower' => strtolower($basename),
            'extension_alias' => Container::underscore($basename),
            'fieldtype_name' => $fieldTypeName,
            'fieldtype_basename' => self::identify($fieldTypeName),
            'fieldtype_identifier' => strtolower(self::identify($fieldTypeName)),
            'yui_fieldtype_namespace' => $yuiFieldTypeNamespace,
            'yui_fieldtype_namespace_identifier' => strtolower(self::identify($yuiFieldTypeNamespace))
        );

        $this->setSkeletonDirs(array($this->kernel->locateResource('@SmileEzFieldTypeGeneratorBundle/Resources/skeleton')));

        $this->renderFile('fieldtype/Bundle.php.twig', $dir.'/'.$bundle.'.php', $parameters);
        $this->renderFile('fieldtype/DependencyInjection/Extension.php.twig', $dir.'/DependencyInjection/'.$basename.'Extension.php', $parameters);
        $this->renderFile('fieldtype/DependencyInjection/Configuration.php.twig', $dir.'/DependencyInjection/Configuration.php', $parameters);
        $this->renderFile('fieldtype/FieldType/Field/SearchField.php.twig', $dir.'/FieldType/'.self::identify($fieldTypeName).'/SearchField.php', $parameters);
        $this->renderFile('fieldtype/FieldType/Field/Type.php.twig', $dir.'/FieldType/'.self::identify($fieldTypeName).'/Type.php', $parameters);
        $this->renderFile('fieldtype/FieldType/Field/Value.php.twig', $dir.'/FieldType/'.self::identify($fieldTypeName).'/Value.php', $parameters);
        $this->renderFile('fieldtype/FieldType/Field/FormMapper.php.twig', $dir.'/FieldType/'.self::identify($fieldTypeName).'/FormMapper.php', $parameters);
        $this->renderFile('fieldtype/FieldType/Field/ValueTransformer.php.twig', $dir.'/FieldType/'.self::identify($fieldTypeName).'/ValueTransformer.php', $parameters);
        $this->renderFile('fieldtype/Persistence/Content/FieldValue/Converter/Converter.php.twig', $dir.'/Persistence/Content/FieldValue/Converter/'.self::identify($fieldTypeName).'Converter.php', $parameters);
        $this->renderFile('fieldtype/Search/FieldType/Field.php.twig', $dir.'/Search/FieldType/'.self::identify($fieldTypeName).'Field.php', $parameters);

        $this->renderFile('fieldtype/Resources/config/css.yml.twig', $dir.'/Resources/config/css.yml', $parameters);
        $this->renderFile('fieldtype/Resources/config/field_templates.yml.twig', $dir.'/Resources/config/field_templates.yml', $parameters);
        $this->renderFile('fieldtype/Resources/config/field_value_converters.yml.twig', $dir.'/Resources/config/field_value_converters.yml', $parameters);
        $this->renderFile('fieldtype/Resources/config/fieldtypes.yml.twig', $dir.'/Resources/config/fieldtypes.yml', $parameters);
        $this->renderFile('fieldtype/Resources/config/indexable_fieldtypes.yml.twig', $dir.'/Resources/config/indexable_fieldtypes.yml', $parameters);
        $this->renderFile('fieldtype/Resources/config/yui.yml.twig', $dir.'/Resources/config/yui.yml', $parameters);
        $this->renderFile('fieldtype/Resources/public/css/views/fields/edit/field.css.twig', $dir.'/Resources/public/css/views/fields/edit/'.strtolower(self::identify($fieldTypeName)).'.css', $parameters);
        $this->renderFile('fieldtype/Resources/public/js/views/fields/editview.js.twig', $dir.'/Resources/public/js/views/fields/'.strtolower(self::identify($yuiFieldTypeNamespace)).'-'.strtolower(self::identify($fieldTypeName)).'-editview.js', $parameters);
        $this->renderFile('fieldtype/Resources/public/js/views/fields/view.js.twig', $dir.'/Resources/public/js/views/fields/'.strtolower(self::identify($yuiFieldTypeNamespace)).'-'.strtolower(self::identify($fieldTypeName)).'-view.js', $parameters);
        $this->renderFile('fieldtype/Resources/public/templates/fields/edit/field.hbt.twig', $dir.'/Resources/public/templates/fields/edit/'.strtolower(self::identify($fieldTypeName)).'.hbt', $parameters);
        $this->renderFile('fieldtype/Resources/public/templates/fields/view/field.hbt.twig', $dir.'/Resources/public/templates/fields/view/'.strtolower(self::identify($fieldTypeName)).'.hbt', $parameters);
        $this->renderFile('fieldtype/Resources/translations/ezrepoforms_content_type.en.yml.twig', $dir.'/Resources/translations/ezrepoforms_content_type.en.yml', $parameters);
        $this->renderFile('fieldtype/Resources/translations/fieldtypes.en.yml.twig', $dir.'/Resources/translations/fieldtypes.en.yml', $parameters);
        $this->renderFile('fieldtype/Resources/views/content_fields.html.twig.twig', $dir.'/Resources/views/content_fields.html.twig', $parameters);
        $this->renderFile('fieldtype/Resources/views/field_types.html.twig.twig', $dir.'/Resources/views/field_types.html.twig', $parameters);
        $this->renderFile('fieldtype/Resources/views/fielddefinition_settings.html.twig.twig', $dir.'/Resources/views/fielddefinition_settings.html.twig', $parameters);
    }

    public static function underscore($id)
    {
        return preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), str_replace(array(' ', '_'), '', $id));
    }

    public static function identify($fieldTypeName)
    {
        $fieldTypeName = self::underscore($fieldTypeName);
        $fieldTypeName = str_replace('_', '', $fieldTypeName);

        return $fieldTypeName;
    }
}
