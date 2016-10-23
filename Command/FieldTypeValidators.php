<?php

namespace Smile\EzFieldTypeGeneratorBundle\Command;

class FieldTypeValidators
{
    public static function validateFieldTypeName($fieldTypeName)
    {
        if (!preg_match('/^[a-zA-Z][ a-zA-Z]*$/', $fieldTypeName)) {
            throw new \InvalidArgumentException('The field type name contains invalid characters.');
        }

        return $fieldTypeName;
    }

    public static function validateFieldTypeNamespace($fieldTypeNamespace)
    {
        if (!preg_match('/^[a-zA-Z]*$/', $fieldTypeNamespace)) {
            throw new \InvalidArgumentException('The field type namespace contains invalid characters.');
        }

        return $fieldTypeNamespace;
    }
}
