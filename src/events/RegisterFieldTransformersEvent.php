<?php

namespace samuelreichoer\queryapi\events;

use craft\base\Event;

class RegisterFieldTransformersEvent extends Event
{
    /**
     * @var array<int, array{fieldClass: string, transformer: class-string}>
     */
    public array $transformers = [];
}
