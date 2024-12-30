<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;

class RegisterElementType extends Model
{
    public string $elementTypeClass = '';
    public string $elementTypeHandle = '';
    public array $allowedMethods = [];
    public string $transformer = '';
}
