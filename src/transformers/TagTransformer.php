<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Tag;

class TagTransformer extends BaseTransformer
{
    private Tag $tag;

    public function __construct(Tag $tag, array $predefinedFields = [])
    {
        parent::__construct($tag, $predefinedFields);
        $this->tag = $tag;
    }

    /**
     * @return array
     */
    public function getTransformedData(): array
    {
        $data = [
            'metadata' => $this->getMetaData(),
            'title' => $this->tag->title,
            'slug' => $this->tag->slug,
        ];

        return $this->smartFilter($data, array_keys($data));
    }

    /**
     * Retrieves metadata from the Tag.
     *
     * @return array
     */
    protected function getMetaData(): array
    {
        return [
            'id' => $this->tag->id,
        ];
    }
}
