<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Address;

class AddressTransformer extends BaseTransformer
{
    private Address $address;

    public function __construct(Address $address)
    {
        $this->address = $address;
    }

    /**
     * @param array $predefinedFields
     * @return array
     */
    public function getTransformedData(array $predefinedFields = []): array
    {
        $metaData = $this->getMetaData();

        return [
        'metadata' => $metaData,
        'title' => $this->address->title ?? '',
        'addressLine1' => $this->address->addressLine1 ?? '',
        'addressLine2' => $this->address->addressLine2 ?? '',
        'addressLine3' => $this->address->addressLine3 ?? '',
        'countryCode' => $this->address->countryCode ?? '',
        'locality' => $this->address->locality ?? '',
        'postalCode' => $this->address->postalCode ?? '',
    ];
    }

    /**
     * Retrieves metadata from the Address.
     *
     * @return array
     */
    protected function getMetaData(): array
    {
        return [
        'id' => $this->address->id,
        'dateCreated' => $this->address->dateCreated,
        'dateUpdated' => $this->address->dateUpdated,
    ];
    }
}
