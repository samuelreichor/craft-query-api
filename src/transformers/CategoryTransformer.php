<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Category;

class CategoryTransformer extends BaseTransformer
{
  private Category $category;

  public function __construct(Category $category)
  {
    parent::__construct($category);
    $this->category = $category;
  }

  /**
   * @return array
   */
  public function getTransformedData(array $predefinedFields = []): array
  {
    $transformedFields = $this->getTransformedFields();

    return array_merge([
        'metadata' => $this->getMetaData(),
        'title' => $this->category->title,
        'slug' => $this->category->slug,
        'uri' => $this->category->uri,
    ], $transformedFields);
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
        'dateCreated' => $this->category->dateCreated,
        'dateUpdated' => $this->category->dateUpdated,
    ];
  }
}
