<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Payments
{
    private $authorizations;

    public function __construct(Authorization ...$authorizations)
    {
        $this->authorizations = $authorizations;
    }

    public function toArray()
    {
        return [

        ];
    }

    /**
     * @return Authorization[]
     **/
    public function authorizations(): array
    {
        return $this->authorizations;
    }
}
