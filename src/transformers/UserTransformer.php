<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\User;

class UserTransformer extends BaseTransformer
{
  private User $user;

  public function __construct(User $user)
  {
    parent::__construct($user);
    $this->user = $user;
  }

  /**
   * @return array
   */
  public function getTransformedData(array $predefinedFields = []): array
  {
    $transformedFields = $this->getTransformedFields();

    return array_merge([
        'metadata' => $this->getMetaData(),
        'username' => $this->user->username,
        'email' => $this->user->email,
        'fullName' => $this->user->fullName,
    ], $transformedFields);
  }

  /**
   * Retrieves metadata from the User.
   *
   * @return array
   */
  protected function getMetaData(): array
  {
    return [
        'id' => $this->user->id,
        'status' => $this->user->status,
        'cpEditUrl' => $this->user->cpEditUrl,
        'dateCreated' => $this->user->dateCreated,
        'dateUpdated' => $this->user->dateUpdated,
    ];
  }
}
