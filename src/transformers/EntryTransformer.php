<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Entry;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use yii\base\InvalidConfigException;

class EntryTransformer extends BaseTransformer
{
    protected Entry $entry;

    public function __construct(Entry $entry)
    {
        parent::__construct($entry);
        $this->entry = $entry;
    }

    /**
     * Transforms the Entry element into an array.
     *
     * @param array $predefinedFields
     * @return array
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    public function getTransformedData(array $predefinedFields = []): array
    {
        $data = ['metadata' => $this->getMetaData()];

        // Not every entry has a section (matrix blocks)
        if ($this->entry->section && isset($this->entry->section->handle)) {
            $data['sectionHandle'] = $this->entry->section->handle;
        }

        $transformedFields = $this->getTransformedFields($predefinedFields);

        return array_merge($data, $transformedFields);
    }

    /**
     * Retrieves metadata from the Entry.
     *
     * @return array
     */
    protected function getMetaData(): array
    {
        return array_merge(parent::getMetaData(), [
        'sectionId' => $this->entry->sectionId,
        'postDate' => $this->entry->postDate,
        'siteId' => $this->entry->siteId,
        'slug' => $this->entry->slug,
        'uri' => $this->entry->uri,
        'cpEditUrl' => $this->entry->cpEditUrl,
        'status' => $this->entry->status,
        'url' => $this->entry->url,
    ]);
    }
}
