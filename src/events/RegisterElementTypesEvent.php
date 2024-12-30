<?php

namespace samuelreichoer\queryapi\events;

use craft\base\Event;
use samuelreichoer\queryapi\models\RegisterElementType;

class RegisterElementTypesEvent extends Event
{
    /**
     * @var RegisterElementType[]
     */
    public array $elementTypes = [];
}
