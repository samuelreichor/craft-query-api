<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\fieldlayoutelements\addresses\AddressField;
use craft\fieldlayoutelements\addresses\CountryCodeField;
use craft\fieldlayoutelements\assets\AltField;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fieldlayoutelements\users\PhotoField;
use craft\fields\Addresses;
use craft\fields\Assets;
use craft\fields\ButtonGroup;
use craft\fields\Categories;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\Country;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Entries;
use craft\fields\Icon;
use craft\fields\Json;
use craft\fields\Lightswitch;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\Money;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Range;
use craft\fields\Table;
use craft\fields\Tags;
use craft\fields\Time;
use craft\fields\Users;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\enums\AssetMode;
use samuelreichoer\queryapi\events\RegisterTypeDefinitionEvent;
use samuelreichoer\queryapi\helpers\AssetHelper;
use samuelreichoer\queryapi\helpers\Typescript;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\QueryApi;

class TypescriptService extends Component
{
    public const EVENT_REGISTER_TYPE_DEFINITIONS = 'registerTypeDefinitions';
    private array $customTypeDefinitions = [];

    public function __construct()
    {
        parent::__construct();
        $this->registerCustomTypeDefinitions();
    }

    public function getTypes(): string
    {
        $schema = [
            'hardTypes' => $this->getHardTypes(),
            'addresses' => $this->getAddressType(),
            'assets' => $this->getAssetType(),
            'entryTypes' => $this->getEntryTypesType(),
            'users' => $this->getUserType(),
            'categories' => $this->getCategoriesTypes(),
            'tables' => $this->getTableTypes(),
            'options' => $this->getOptionTypes(),
        ];

        if (count($this->customTypeDefinitions) > 0) {
            $schema['customHardTypes'] = $this->getCustomHardTypes();
        }

        $ts = $this->getAsciiBanner();

        foreach ($schema as $key => $types) {
            $ts .= "// --- $key ---\n";
            $ts .= $types . "\n\n";
        }

        return $ts;
    }

    protected function registerCustomTypeDefinitions(): void
    {
        if ($this->hasEventHandlers(self::EVENT_REGISTER_TYPE_DEFINITIONS)) {
            $event = new RegisterTypeDefinitionEvent();
            $this->trigger(self::EVENT_REGISTER_TYPE_DEFINITIONS, $event);
            $this->customTypeDefinitions = $event->typeDefinitions;
        }
    }

    protected function getCustomHardTypes(): string
    {
        $ts = '';

        foreach ($this->customTypeDefinitions as $definition) {
            if (!$definition['staticHardType'] && !$definition['dynamicHardType']) {
                continue;
            }

            // dynamicHardType refers to a class that returns the type by calling setCustomHardTypes()
            if (!empty($definition['dynamicHardType'])) {
                $dynamicClass = $definition['dynamicHardType'];

                if (!class_exists($dynamicClass)) {
                    Craft::error("Class {$dynamicClass} not found, dynamic hard type not applied.", 'queryApi');
                    break;
                }

                $transformer = new $dynamicClass();

                if (!method_exists($transformer, 'setCustomHardTypes')) {
                    Craft::error("Class {$dynamicClass} missing 'setCustomHardTypes' method, dynamic hard type not applied.", 'queryApi');
                    break;
                }

                $ts .= $transformer->setCustomHardTypes();
                continue;
            }

            // staticHardType defines a fixed TypeScript type to assign without further logic
            if (!empty($definition['staticHardType'])) {
                $ts .= $definition['staticHardType'];
            }
        }

        return $ts;
    }

    protected function getTypesByFieldLayout(FieldLayout $fieldLayout): array
    {
        $fieldElements = array_merge($fieldLayout->getElementsByType(BaseField::class), $fieldLayout->getElementsByType(CustomField::class));
        $tsFieldTypes = [];
        foreach ($fieldElements as $field) {
            $fieldClass = get_class($field);

            // only include title type in entry types if the entry type actually has a title field.
            if ($field instanceof EntryTitleField) {
                // @phpstan-ignore-next-line
                if (property_exists($fieldLayout->provider, 'hasTitleField') && !$fieldLayout->provider->hasTitleField) {
                    continue;
                }
            }

            // only custom fields have the getField() method
            if (method_exists($field, 'getField')) {
                $field = $field->getField();
                $fieldClass = get_class($field);
            }

            // skip all excluded field classes
            if (in_array($fieldClass, $this->getExcludedFieldClasses(), true)) {
                continue;
            }
            // skip addressField field as it gets managed by $this->getAddressType()
            if ($fieldClass === AddressField::class) {
                continue;
            }

            $fieldHandle = $field->handle ?? $field->attribute ?? 'unknown';

            // Check for custom type definition
            foreach ($this->customTypeDefinitions as $definition) {
                if ($definition['fieldTypeClass'] !== $fieldClass) {
                    continue;
                }

                // dynamicDefinitionClass refers to a class that returns the type by calling setTypeByField(field)
                if (!empty($definition['dynamicDefinitionClass'])) {
                    $dynamicClass = $definition['dynamicDefinitionClass'];

                    if (!class_exists($dynamicClass)) {
                        Craft::error("Class {$dynamicClass} not found, dynamic type definition not applied.", 'queryApi');
                        break;
                    }

                    $transformer = new $dynamicClass($field);

                    if (!method_exists($transformer, 'setTypeByField')) {
                        Craft::error("Dynamic type definition {$dynamicClass} missing 'setTypeByField' method.", 'queryApi');
                        break;
                    }

                    $tsFieldTypes[$fieldHandle] = $transformer->setTypeByField($field);
                    // break out to pass matcher and prevent overwrites
                    continue 2;
                }

                // staticTypeDefinition defines a fixed TypeScript type to assign without further logic
                if (!empty($definition['staticTypeDefinition'])) {
                    $tsFieldTypes[$fieldHandle] = $definition['staticTypeDefinition'];
                    // break out to pass matcher and prevent overwrites
                    continue 2;
                }
            }

            if (property_exists($field, 'type') && $field->type === 'text') {
                $tsType = $this->modifyTypeByField($field, 'string');
            } else {
                $tsType = match ($fieldClass) {
                    PlainText::class, Icon::class, Email::class, CountryCodeField::class, AltField::class, 'craft\ckeditor\Field' => $this->modifyTypeByField($field, 'string'), // @phpstan-ignore-line
                    Range::class, Number::class => $this->modifyTypeByField($field, 'number'),
                    Lightswitch::class => $this->modifyTypeByField($field, 'boolean'),
                    Date::class, Time::class => $this->modifyTypeByField($field, 'CraftDateTime'),
                    Color::class => $this->modifyTypeByField($field, 'CraftColor'),
                    Country::class => $this->modifyTypeByField($field, 'CraftCountry'),
                    Money::class => $this->modifyTypeByField($field, 'CraftMoney'),
                    Link::class => $this->modifyTypeByField($field, 'CraftLink'),
                    Json::class => $this->modifyTypeByField($field, 'CraftJson'),
                    Tags::class => $this->modifyTypeByField($field, 'CraftTag'),
                    Assets::class, PhotoField::class => $this->modifyTypeByField($field, 'CraftAsset'),
                    Users::class => $this->modifyTypeByField($field, 'CraftUser'),
                    Addresses::class => $this->modifyTypeByField($field, 'CraftAddress'),
                    Entries::class => $this->modifyTypeByField($field, 'CraftEntryRelation'),
                    Dropdown::class, RadioButtons::class, Checkboxes::class, MultiSelect::class, ButtonGroup::class => $this->modifyTypeByField($field, $this->getOptionTypeByField($field)),
                    Matrix::class => $this->modifyTypeByField($field, $this->getEntryTypesByField($field)),
                    Table::class => $this->modifyTypeByField($field, $this->getTableTypeByField($field)),
                    Categories::class => $this->modifyTypeByField($field, $this->getCategoryTypeByField($field)),
                    default => 'any',
                };
            }

            $tsFieldTypes[$fieldHandle] = $tsType;
        }

        return $tsFieldTypes;
    }

    protected function modifyTypeByField($field, string $rawType): string
    {
        // rawType is for example CraftDateTime
        $type = $rawType;

        // type should be (CraftDateTime)[]
        $isSingleRelation = Utils::isArrayField($field);
        if ($isSingleRelation) {
            $type = '(' . $type . ')[]';
        }

        // type should be CraftDateTime | null
        $isRequiredField = Utils::isRequiredField($field);
        if (!$isRequiredField) {
            $type .= ' | null';
        }

        return $type;
    }

    protected function getAssetType(): string
    {
        $assetType = '';
        $baseAssetType = [
            'metadata' => 'CraftAssetMeta',
            'height' => 'number',
            'width' => 'number',
            'focalPoint' => 'CraftAssetFocalPoint',
            'url' => 'string',
            'title' => 'string',
        ];

        $imageMode = AssetHelper::getAssetMode();
        if ($imageMode === AssetMode::IMAGERX) {
            $srcSetKeys = AssetHelper::getImagerXTransformKeys();
            $srcSetArr = [];
            foreach ($srcSetKeys as $srcSetKey) {
                $srcSetArr[$srcSetKey] = 'string';
            }

            $assetType .= Typescript::buildTsType($srcSetArr, 'CraftAssetRatio') . "\n\n";
            $baseAssetType['srcSets'] = 'CraftAssetRatio';
        }

        $volumes = Craft::$app->volumes->getAllVolumes();
        $allAssetTypes = [];
        foreach ($volumes as $volume) {
            $volumeName = StringHelper::toPascalCase($volume->handle);
            $fieldTypes = $this->getTypesByFieldLayout($volume->getFieldLayout());
            $typeName = 'CraftVolume' . $volumeName;
            $allAssetTypes[] = $typeName;
            $assetType .= Typescript::buildTsType(array_merge($baseAssetType, $fieldTypes), $typeName) . "\n\n";
        }

        $assetType .= 'export type CraftAsset = ' . implode(' | ', $allAssetTypes);

        return $assetType;
    }

    protected function getAddressType(): string
    {
        $baseAddressType = [
            'metadata' => 'CraftAddressMeta',
            'title' => 'string',
            'addressLine1' => 'string',
            'addressLine2' => 'string',
            'addressLine3' => 'string',
            'countryCode' => 'string',
            'locality' => 'string',
            'postalCode' => 'string',
        ];
        $fieldLayout = Craft::$app->addresses->getFieldLayout();
        $fieldTypes = $this->getTypesByFieldLayout($fieldLayout);
        return Typescript::buildTsType(array_merge($baseAddressType, $fieldTypes), 'CraftAddress');
    }

    protected function getUserType(): string
    {
        $baseAddressType = [
            'metadata' => 'CraftUserMeta',
        ];

        $userFieldLayout = Craft::$app->getFields()->getLayoutByType(User::class);
        $fieldTypes = $this->getTypesByFieldLayout($userFieldLayout);
        return Typescript::buildTsType(array_merge($baseAddressType, $fieldTypes), 'CraftUser');
    }

    protected function getEntryTypesType(): string
    {
        $ts = '';
        // If entry type used in matrix or elsewhere = CraftEntryType + Name
        // If entry type used in section = CraftEntryPage + Name
        $allEntryTypes = Craft::$app->entries->getAllEntryTypes();
        foreach ($allEntryTypes as $entryType) {
            $typeName = 'CraftEntryType' . StringHelper::toPascalCase($entryType->handle);
            $fieldTypes = $this->getTypesByFieldLayout($entryType->getFieldLayout());
            $ts .= Typescript::buildTsType($fieldTypes, $typeName, 'interface') . "\n\n";
        }

        $generatedEntryPageTypes = [];
        $allSections = Craft::$app->entries->getAllSections();

        foreach ($allSections as $section) {
            foreach ($section->getEntryTypes() as $entryType) {
                $handle = StringHelper::toPascalCase($entryType->handle);
                $typeName = 'CraftPage' . $handle;

                // Already generated
                if (in_array($typeName, $generatedEntryPageTypes, true)) {
                    continue;
                }

                $refTypeName = 'CraftEntryType' . $handle;

                $ts .= "export interface {$typeName} extends {$refTypeName} {\n";
                $ts .= "    metadata: CraftEntryMeta\n";
                $ts .= "    title: string\n";
                $ts .= "    sectionHandle: string\n";
                $ts .= "}\n\n";

                $generatedEntryPageTypes[] = $typeName;
            }
        }

        return $ts;
    }

    protected function getTableTypes(): string
    {
        $ts = '';
        $allTableFields = Craft::$app->fields->getFieldsByType(Table::class);
        foreach ($allTableFields as $field) {
            $typeName = 'CraftTable' . StringHelper::toPascalCase($field->handle);

            $columns = $field->columns ?? [];
            if (empty($columns)) {
                continue;
            }

            $fieldTypes = [];
            foreach ($columns as $colKey => $colData) {
                $fieldTypes[$colKey] = 'string';

                if (!empty($colData['handle'])) {
                    $fieldTypes[$colData['handle']] = 'string';
                }
            }

            $ts .= Typescript::buildTsType($fieldTypes, $typeName, 'interface') . "\n\n";
        }
        return $ts;
    }

    protected function getOptionTypes(): string
    {
        $ts = '';
        $baseOptionTypes = [
            'label' => 'string',
            'selected' => 'boolean',
            'valid' => 'boolean',
            'icon' => 'string | null',
            'color' => 'string | null',
        ];

        $optionClasses = [Dropdown::class, RadioButtons::class, Checkboxes::class, MultiSelect::class, 'craft\fields\ButtonGroup'];
        $fields = [];
        foreach ($optionClasses as $optionClass) {
            $fields = array_merge($fields, Craft::$app->getFields()->getFieldsByType($optionClass));
        }

        foreach ($fields as $field) {
            $handle = StringHelper::toPascalCase($field->handle);
            $OptionTypeName = 'CraftOptionValue' . $handle;
            $optionValues = array_map(fn($option) => "'" . $option['value'] . "'", $field['options']);
            $optionValueType = implode(' | ', $optionValues);
            $ts .= "export type {$OptionTypeName} = {$optionValueType}\n\n";
            $baseOptionTypes['value'] = $OptionTypeName;

            $typeName = 'CraftOption' . $handle;
            $ts .= Typescript::buildTsType($baseOptionTypes, $typeName) . "\n\n";
        }

        return $ts;
    }

    protected function getCategoriesTypes(): string
    {
        $ts = '';
        $allCategoryGroups = Craft::$app->categories->getAllGroups();
        foreach ($allCategoryGroups as $group) {
            $handle = StringHelper::toPascalCase($group->handle);
            $typeName = 'CraftCategory' . $handle;
            $fieldTypes = $this->getTypesByFieldLayout($group->getFieldLayout());
            $ts .= Typescript::buildTsType($fieldTypes, $typeName, 'interface') . "\n\n";
        }

        return $ts;
    }

    protected function getEntryTypesByField(Matrix $field): string
    {
        $allAvailableMatrixBlockTypes = $field->getEntryTypes();
        $availableTypes = [];
        foreach ($allAvailableMatrixBlockTypes as $entryType) {
            $availableTypes[] = 'CraftEntryType' . StringHelper::toPascalCase($entryType->handle);
        }

        return implode(' | ', $availableTypes);
    }

    protected function getTableTypeByField(Table $field): string
    {
        return 'CraftTable' . StringHelper::toPascalCase($field->handle);
    }

    protected function getOptionTypeByField($field): string
    {
        return 'CraftOption' . StringHelper::toPascalCase($field->handle);
    }

    protected function getCategoryTypeByField(Categories $field): string
    {
        $categoryUid = explode(':', $field->source)[1];
        $group = Craft::$app->categories->getGroupByUid($categoryUid);

        if (!$group) {
            return 'unknown';
        }

        $handle = StringHelper::toPascalCase($group->handle);
        return 'CraftCategory' . $handle;
    }

    protected function getExcludedFieldClasses(): array
    {
        if (isset(QueryApi::getInstance()->getSettings()->excludeFieldClasses)) {
            return array_merge(Constants::EXCLUDED_FIELD_HANDLES, QueryApi::getInstance()->getSettings()->excludeFieldClasses);
        }

        return Constants::EXCLUDED_FIELD_HANDLES;
    }

    protected function getHardTypes(): string
    {
        return <<<TS
        export type CraftDateTime = {
            date: string
            timezone: string
            timezone_type: number
        }
        
        export type CraftColor = {
            hex: string
            rgb: string
            hsl: string
        }
        
        export type CraftCountry = {
            name: string
            countryCode: string
            threeLetterCode: string
            locale: string
            currencyCode: string
            timezones: string[]
        }
        
        export type CraftMoney = {
            amount: string
            currency: string
        }
        
        export type CraftLinkTarget = '_blank' | '_self'
        
        export type CraftLink = {
            elementType: string
            url: string
            label: string
            target: CraftLinkTarget
            rel: string
            urlSuffix: string
            class: string
            id: string
            ariaLabel: string
            download: boolean
            downloadFile: string
        }
        
        export type CraftJson = object | object[]
        
        export type CraftAssetFocalPoint = {
            x: number
            y: number
        }
        
        export type CraftAssetMeta = {
            id: number
            filename: string
            kind: string
            size: string
            mimeType: string
            extension: string
            cpEditUrl: string
            volumeId: number
        }
        
        export type CraftAddressMeta = {
            id: number
        }
        
        export type CraftUserStatus = 'inactive' | 'active' | 'pending' | 'credentialed' | 'suspended' | 'locked'
        
        export type CraftUserMeta = {
            id: number
            status: CraftUserStatus
            cpEditUrl: string
        }
        
        export type CraftEntryRelation = {
            title: string
            slug: string
            url: string
            id: number
        }
        
        export type CraftEntryStatus = 'live' | 'pending' | 'expired' | 'disabled'
        
        export type CraftEntryMeta = {
            id: number
            entryType: string
            sectionId: number
            siteId: number
            url: string
            slug: string
            uri: string
            fullUri: string
            status: CraftEntryStatus
            cpEditUrl: string
        }
        
        export interface CraftPageBase {
            metadata: CraftEntryMeta
            sectionHandle: string
            title: string
        }
        
        export type CraftTagMeta = {
            id: number
        }
        
        export type CraftTag = {
            metadata: CraftTagMeta
            title: string
            slug: string
        }
        TS;
    }

    protected function getAsciiBanner(): string
    {
        $ascii = <<<ASCII
        
           ___                             _    ____ ___ 
          / _ \ _   _  ___ _ __ _   _     / \  |  _ \_ _|
         | | | | | | |/ _ \ '__| | | |   / _ \ | |_) | | 
         | |_| | |_| |  __/ |  | |_| |  / ___ \|  __/| | 
          \__\_\\__,_|\___|_|   \__, | /_/   \_\_|  |___|
                                |___/                    
                  
        This file was generated by the Query API Type Generator.
        Please do not edit this file manually. Changes made in this file might get lost.
        To update it, run the 'craft query-api/typescript/generate-types' command.
        ASCII;

        $tsComment = "/**\n";
        foreach (explode("\n", $ascii) as $line) {
            $tsComment .= " * $line\n";
        }
        $tsComment .= " */\n\n";

        return $tsComment;
    }
}
