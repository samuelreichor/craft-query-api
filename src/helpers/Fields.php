<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;

class Fields
{
    public static function getAllFieldElementsByLayout(FieldLayout $fieldLayout): array
    {
        $generatedFields = method_exists($fieldLayout, 'getGeneratedFields')
            ? $fieldLayout->getGeneratedFields()
            : [];

        $baseFields = $fieldLayout->getElementsByType(BaseField::class);
        $customFields = $fieldLayout->getElementsByType(CustomField::class);

        return array_merge($baseFields, $customFields, $generatedFields);
    }

    public static function isGeneratedField(mixed $field): bool
    {
        if (is_array($field) && isset($field['handle']) && isset($field['uid'])) {
            return true;
        }

        return false;
    }

    public static function getGeneratedFieldHandle(array $field): string
    {
        if (!isset($field['handle'])) {
            Craft::error('Cannot process array field: ' . serialize($field), 'queryApi');
            return '';
        }

        return $field['handle'];
    }

    public static function getGeneratedFieldUid(array $field): string
    {
        if (!isset($field['uid'])) {
            Craft::error('Cannot process array field: ' . serialize($field), 'queryApi');
            return '';
        }

        return $field['uid'];
    }
}
