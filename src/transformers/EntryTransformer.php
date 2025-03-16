<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Entry;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use samuelreichoer\queryapi\helpers\Utils;
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
        $isEntryWithSection = $this->entry->section !== null;
        return array_merge(parent::getMetaData(), [
            'entryType' => $this->entry->type->getHandle(),
            'sectionId' => $isEntryWithSection ? $this->entry->section->getId() : null,
            'siteId' => $this->entry->site->id,
            'url' => $this->entry->getUrl(),
            'slug' => $this->entry->slug,
            'uri' => $this->entry->uri,
            'fullUri' => Utils::getFullUriFromUrl($this->entry->getUrl()),
            'status' => $this->entry->getStatus(),
            'cpEditUrl' => $this->entry->getCpEditUrl(),
        ]);
    }
}
