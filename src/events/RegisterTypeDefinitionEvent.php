<?php

namespace samuelreichoer\queryapi\events;

use craft\base\Event;
use samuelreichoer\queryapi\models\RegisterTypeDefinition;

class RegisterTypeDefinitionEvent extends Event
{
    /**
     * @var RegisterTypeDefinition[]
     */
    public array $typeDefinitions = [];
}
