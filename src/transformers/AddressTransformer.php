<?php

namespace samuelreichoer\queryapi\transformers;

use craft\elements\Address;

class AddressTransformer extends BaseTransformer
{
    private Address $address;

    public function __construct(Address $address, array $predefinedFields = [])
    {
        parent::__construct($address, $predefinedFields);
        $this->address = $address;
    }

    /**
     * @return array
     */
    public function getTransformedData(): array
    {
        $data = [
            'metadata' => $this->getMetaData(),
            'title' => $this->address->title ?? '',
            'addressLine1' => $this->address->addressLine1 ?? '',
            'addressLine2' => $this->address->addressLine2 ?? '',
            'addressLine3' => $this->address->addressLine3 ?? '',
            'countryCode' => $this->address->countryCode ?? '',
            'locality' => $this->address->locality ?? '',
            'postalCode' => $this->address->postalCode ?? '',
        ];

        return $this->smartFilter($data, array_keys($data));
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
        ];
    }
}
