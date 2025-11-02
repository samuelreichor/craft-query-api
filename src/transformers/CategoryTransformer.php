<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Category;

class CategoryTransformer extends BaseTransformer
{
    private Category $category;

    public function __construct(Category $category, array $predefinedFields = [])
    {
        parent::__construct($category, $predefinedFields);
        $this->category = $category;
    }

    /**
     * @return array
     */
    public function getTransformedData(): array
    {
        $transformedFields = $this->getTransformedFields();

        $data = [
            'metadata' => $this->getMetaData(),
            'title' => $this->category->title,
            'slug' => $this->category->slug,
            'uri' => $this->category->uri,
        ];

        $fullData = array_merge($data, $transformedFields);

        return $this->smartFilter($fullData, array_keys($data));
    }

    /**
     * Retrieves metadata from the Category.
     *
     * @return array
     */
    protected function getMetaData(): array
    {
        return [
            'id' => $this->category->id,
        ];
    }
}
