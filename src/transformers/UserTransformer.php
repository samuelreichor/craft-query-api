<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\User;

class UserTransformer extends BaseTransformer
{
    private User $user;

    public function __construct(User $user, array $predefinedFields = [])
    {
        parent::__construct($user, $predefinedFields);
        $this->user = $user;
    }

    /**
     * @return array
     */
    public function getTransformedData(): array
    {
        $transformedFields = $this->getTransformedFields();

        $data = [
            'metadata' => $this->getMetaData(),
            'username' => $this->user->username,
            'email' => $this->user->email,
            'fullName' => $this->user->fullName,
        ];

        $fullData = array_merge($data, $transformedFields);

        return $this->smartFilter($fullData, array_keys($data));
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
        ];
    }
}
