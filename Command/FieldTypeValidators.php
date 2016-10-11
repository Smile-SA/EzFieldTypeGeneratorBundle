<?php

namespace Smile\EzFieldTypeGeneratorBundle\Command;

class FieldTypeValidators
{
    public static function validateFieldTypeName($fieldTypeName)
    {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][ a-zA-Z0-9_\x7f-\xff]*$/', $fieldTypeName)) {
            throw new \InvalidArgumentException('The field type name contains invalid characters.');
        }

        return $fieldTypeName;
    }
}
