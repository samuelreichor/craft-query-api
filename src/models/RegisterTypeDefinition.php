<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;

class RegisterTypeDefinition extends Model
{
    public string $fieldTypeClass = '';
    public string $staticHardType = '';
    public string $dynamicHardType = '';
    public string $staticTypeDefinition = '';
    public string $dynamicDefinitionClass = '';
}
