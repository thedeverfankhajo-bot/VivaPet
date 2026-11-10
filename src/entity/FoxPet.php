<?php

declare(strict_types=1);

namespace VivaPet\entity;

final class FoxPet extends PetEntity{
    public static function getNetworkTypeId() : string{ return "vivapet:fox"; }
}
