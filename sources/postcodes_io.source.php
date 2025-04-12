<?php
namespace STPH\addressAutoComplete;

if (!class_exists("source")) require_once(__DIR__ . "/../classes/source.class.php");

class postcodes_io extends source {

    public function mapAddress($value) {

        $address = new Address;

        // Set label and value to the postcode
        $address->label = $value;
        $address->value = $value;

        // postcodes.io does not provide detailed address parts in autocomplete
        $address->parts->street = null;
        $address->parts->number = null;
        $address->parts->code = $value;
        $address->parts->city = null;

        // No additional metadata available in autocomplete
        $address->meta->x = null;
        $address->meta->y = null;
        $address->meta->id = $value;

        return $address;
    }
}
