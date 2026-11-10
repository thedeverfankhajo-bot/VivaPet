<?php

declare(strict_types=1);

namespace VivaPet\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

abstract class PetEntity extends Living implements NeverSavedWithChunkEntity{

    public function __construct(
        Location $location,
        private readonly string $ownerUuid,
        private readonly string $petId,
        private readonly string $displayName,
        float $scale = 1.0
    ){
        parent::__construct($location);
        $this->setCanSaveWithChunk(false);
        $this->setHasGravity(false);
        $this->setGravity(0.0);
        $this->setSilent(true);
        $this->setNoClientPredictions(true);
        $this->setNameTag($displayName);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setScale(max(0.35, min(1.5, $scale)));
    }

    final public function getName() : string{ return $this->displayName; }
    final public function getOwnerUuid() : string{ return $this->ownerUuid; }
    final public function getPetId() : string{ return $this->petId; }
    final public function canBeRenamed() : bool{ return false; }
    final public function canBeCollidedWith() : bool{ return false; }
    final public function canCollideWith(Entity $entity) : bool{ return false; }

    final protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.0, 0.72, 0.82);
    }

    final public function stopMoving() : void{
        $this->setMotion(new Vector3(0.0, 0.0, 0.0));
    }

    final public function belongsTo(Player $player) : bool{
        return $player->getUniqueId()->toString() === $this->ownerUuid;
    }
}
