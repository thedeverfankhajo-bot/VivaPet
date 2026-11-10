<?php

declare(strict_types=1);

namespace VivaPet\entity;

final class DragonPet extends PetEntity{
    public static function getNetworkTypeId() : string{ return "vivapet:dragon"; }
}
