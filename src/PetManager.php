<?php

declare(strict_types=1);

namespace VivaPet;

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use VivaPet\entity\DragonPet;
use VivaPet\entity\FoxPet;
use VivaPet\entity\OwlPet;
use VivaPet\entity\PetEntity;
use VivaPet\entity\RabbitPet;

final class PetManager{

    /** @var array<string, class-string<PetEntity>> */
    private const ENTITY_CLASSES = [
        "fox" => FoxPet::class,
        "dragon" => DragonPet::class,
        "owl" => OwlPet::class,
        "rabbit" => RabbitPet::class
    ];

    /** @var array<string, callable(): Effect> */
    private const EFFECTS = [
        "speed" => [VanillaEffects::class, "SPEED"],
        "strength" => [VanillaEffects::class, "STRENGTH"],
        "jump_boost" => [VanillaEffects::class, "JUMP_BOOST"],
        "resistance" => [VanillaEffects::class, "RESISTANCE"],
        "night_vision" => [VanillaEffects::class, "NIGHT_VISION"],
        "regeneration" => [VanillaEffects::class, "REGENERATION"],
        "fire_resistance" => [VanillaEffects::class, "FIRE_RESISTANCE"],
        "haste" => [VanillaEffects::class, "HASTE"],
        "water_breathing" => [VanillaEffects::class, "WATER_BREATHING"],
        "absorption" => [VanillaEffects::class, "ABSORPTION"]
    ];

    private Config $playerData;

    /** @var array<string, array{player: Player, pet: PetEntity, petId: string, ticks: int}> */
    private array $activePets = [];

    public function __construct(private readonly Main $plugin){
        $this->playerData = new Config($plugin->getDataFolder() . "players.yml", Config::YAML, []);
    }

    /** @return string[] */
    public function getPetIds() : array{
        $configured = $this->plugin->getConfig()->get("pets", []);
        if(!is_array($configured)){
            return [];
        }
        return array_values(array_filter(
            array_keys($configured),
            fn(mixed $id) : bool => is_string($id) && isset(self::ENTITY_CLASSES[$id]) && $this->getPetDefinition($id) !== null
        ));
    }

    /** @return array<string, mixed>|null */
    public function getPetDefinition(string $petId) : ?array{
        if(!preg_match('/^[a-z0-9_]{1,24}$/D', $petId) || !isset(self::ENTITY_CLASSES[$petId])){
            return null;
        }
        $pets = $this->plugin->getConfig()->get("pets", []);
        return is_array($pets) && isset($pets[$petId]) && is_array($pets[$petId]) ? $pets[$petId] : null;
    }

    public function getSelectedPetId(Player $player) : ?string{
        $saved = $this->playerData->get($player->getUniqueId()->toString(), null);
        if(!is_string($saved) || $saved === "" || $this->getPetDefinition($saved) === null){
            return null;
        }
        return $saved;
    }

    public function getActivePetId(Player $player) : ?string{
        $uuid = $player->getUniqueId()->toString();
        if(!isset($this->activePets[$uuid])){
            return null;
        }
        $state = $this->activePets[$uuid];
        if(!$state["player"]->isOnline() || $state["pet"]->isClosed() || !$state["pet"]->isAlive()){
            return null;
        }
        return $state["petId"];
    }

    /**
     * Hides the currently spawned companion without forgetting the player's selection.
     * @return bool True if an active pet entry was removed.
     */
    public function hide(Player $player) : bool{
        $uuid = $player->getUniqueId()->toString();
        if(!isset($this->activePets[$uuid])){
            return false;
        }
        $this->removeActiveByUuid($uuid);
        return true;
    }

    /** @return "ok"|"none"|"unknown"|"denied"|"offline" */
    public function showSelected(Player $player) : string{
        $saved = $this->playerData->get($player->getUniqueId()->toString(), null);
        if(!is_string($saved) || $saved === ""){
            return "none";
        }
        if($this->getPetDefinition($saved) === null){
            return "unknown";
        }
        return $this->summon($player, $saved);
    }

    public function hasPetPermission(Player $player, string $petId) : bool{
        $definition = $this->getPetDefinition($petId);
        if($definition === null){
            return false;
        }
        $permission = $definition["permission"] ?? ("vivapet.pet." . $petId);
        if(!is_string($permission) || !preg_match('/^[a-zA-Z0-9._-]{1,96}$/D', $permission)){
            return false;
        }
        return $player->hasPermission("vivapet.pet.all") || $player->hasPermission($permission);
    }

    /** @return "ok"|"unknown"|"denied"|"offline" */
    public function summon(Player $player, string $petId) : string{
        $definition = $this->getPetDefinition($petId);
        if($definition === null){
            return "unknown";
        }
        if(!$player->isOnline()){
            return "offline";
        }
        if(!$this->hasPetPermission($player, $petId)){
            return "denied";
        }

        $uuid = $player->getUniqueId()->toString();
        $this->removeActiveByUuid($uuid);

        $class = self::ENTITY_CLASSES[$petId];
        $scaleValue = $definition["scale"] ?? 1.0;
        $scale = is_numeric($scaleValue) ? max(0.35, min(1.5, (float) $scaleValue)) : 1.0;
        $name = is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);

        $pet = new $class($this->getFollowLocation($player), $uuid, $petId, $name, $scale);
        $pet->setOwningEntity($player);
        $pet->spawnToAll();

        $this->activePets[$uuid] = ["player" => $player, "pet" => $pet, "petId" => $petId, "ticks" => 0];
        $this->playerData->set($uuid, $petId);
        $this->playerData->save();
        $this->applyPetEffects($player, $definition);
        return "ok";
    }

    public function remove(Player $player, bool $forgetSelection = true) : void{
        $uuid = $player->getUniqueId()->toString();
        $this->removeActiveByUuid($uuid);
        if($forgetSelection){
            $this->playerData->remove($uuid);
            $this->playerData->save();
        }
    }

    public function handleJoin(Player $player) : void{
        if(!(bool) $this->plugin->getConfig()->getNested("settings.auto-summon", true)){
            return;
        }
        $this->showSelected($player);
    }

    public function removeForPlayer(Player $player) : void{
        $this->removeActiveByUuid($player->getUniqueId()->toString());
    }

    public function shutdown() : void{
        foreach(array_keys($this->activePets) as $uuid){
            $this->removeActiveByUuid($uuid);
        }
    }

    public function tick() : void{
        $settings = $this->plugin->getConfig()->get("settings", []);
        $settings = is_array($settings) ? $settings : [];
        $followDistance = $this->boundedFloat($settings["follow-distance"] ?? 1.65, 0.75, 6.0, 1.65);
        $teleportDistance = $this->boundedFloat($settings["teleport-distance"] ?? 14.0, 6.0, 64.0, 14.0);
        $followSpeed = $this->boundedFloat($settings["follow-speed"] ?? 0.28, 0.05, 0.8, 0.28);
        $effectRefresh = $this->boundedInt($settings["effect-refresh-ticks"] ?? 40, 10, 200, 40);
        $interval = $this->boundedInt($settings["follow-interval-ticks"] ?? 2, 1, 10, 2);
        $effectSteps = max(1, (int) ceil($effectRefresh / $interval));

        foreach(array_keys($this->activePets) as $uuid){
            if(!isset($this->activePets[$uuid])){
                continue;
            }
            $state = $this->activePets[$uuid];
            $player = $state["player"];
            $pet = $state["pet"];
            $petId = $state["petId"];

            if(!$player->isOnline() || $pet->isClosed() || !$pet->isAlive()){
                $this->removeActiveByUuid($uuid);
                continue;
            }
            $definition = $this->getPetDefinition($petId);
            if($definition === null || !$this->hasPetPermission($player, $petId)){
                $this->removeActiveByUuid($uuid);
                continue;
            }
            if($pet->getWorld() !== $player->getWorld()){
                $this->removeActiveByUuid($uuid);
                $this->summon($player, $petId);
                continue;
            }

            $ticks = $state["ticks"] + 1;
            $this->activePets[$uuid]["ticks"] = $ticks;
            if(($ticks % $effectSteps) === 0){
                $this->applyPetEffects($player, $definition);
            }

            $target = $this->getFollowLocation($player);
            $delta = new Vector3(
                $target->x - $pet->getPosition()->x,
                $target->y - $pet->getPosition()->y,
                $target->z - $pet->getPosition()->z
            );
            $distanceSquared = $delta->lengthSquared();
            if($distanceSquared > ($teleportDistance * $teleportDistance)){
                $pet->teleport($target);
                $pet->stopMoving();
            }elseif($distanceSquared > ($followDistance * $followDistance)){
                $distance = sqrt($distanceSquared);
                $remainingDistance = max(0.0, $distance - $followDistance);
                $movementSpeed = min($followSpeed, max(0.04, $remainingDistance * 0.45));
                $pet->setMotion($delta->normalize()->multiply($movementSpeed));
            }else{
                $pet->stopMoving();
            }
        }
    }

    /** @param array<string, mixed> $definition */
    private function applyPetEffects(Player $player, array $definition) : void{
        $effects = $definition["effects"] ?? [];
        if(!is_array($effects)){
            return;
        }
        $settings = $this->plugin->getConfig()->get("settings", []);
        $durationValue = is_array($settings) ? ($settings["effect-duration-ticks"] ?? 100) : 100;
        $refreshValue = is_array($settings) ? ($settings["effect-refresh-ticks"] ?? 40) : 40;
        $intervalValue = is_array($settings) ? ($settings["follow-interval-ticks"] ?? 2) : 2;
        $refreshTicks = $this->boundedInt($refreshValue, 10, 200, 40);
        $followInterval = $this->boundedInt($intervalValue, 1, 10, 2);
        $duration = max(
            $this->boundedInt($durationValue, 40, 1200, 100),
            $refreshTicks + $followInterval + 5
        );

        foreach($effects as $effectName => $effectConfig){
            if(!is_string($effectName) || !isset(self::EFFECTS[$effectName]) || !is_array($effectConfig)){
                continue;
            }
            $factory = self::EFFECTS[$effectName];
            $effect = $factory();
            $amplifier = $this->boundedInt($effectConfig["amplifier"] ?? 0, 0, 4, 0);
            $visible = (bool) ($effectConfig["visible"] ?? false);
            $current = $player->getEffects()->get($effect);

            if($current !== null && $current->getAmplifier() > $amplifier){
                continue;
            }
            if($current !== null && $current->getAmplifier() === $amplifier && $current->getDuration() > (int) ($duration * 0.65)){
                continue;
            }
            $player->getEffects()->add(new EffectInstance($effect, $duration, $amplifier, $visible, false));
        }
    }

    private function getFollowLocation(Player $player) : Location{
        $position = $player->getPosition();
        $yaw = $player->getLocation()->getYaw();
        $radians = deg2rad($yaw);
        $settings = $this->plugin->getConfig()->get("settings", []);
        $settings = is_array($settings) ? $settings : [];
        $offset = $this->boundedFloat($settings["follow-offset"] ?? 1.55, 0.75, 3.5, 1.55);
        $heightOffset = $this->boundedFloat($settings["follow-height-offset"] ?? 0.12, -1.0, 2.0, 0.12);
        return new Location(
            $position->x + sin($radians) * $offset,
            $position->y + $heightOffset,
            $position->z - cos($radians) * $offset,
            $player->getWorld(),
            $yaw,
            0.0
        );
    }

    private function removeActiveByUuid(string $uuid) : void{
        if(!isset($this->activePets[$uuid])){
            return;
        }
        $pet = $this->activePets[$uuid]["pet"];
        unset($this->activePets[$uuid]);
        if(!$pet->isClosed()){
            $pet->close();
        }
    }

    private function boundedInt(mixed $value, int $min, int $max, int $default) : int{
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    private function boundedFloat(mixed $value, float $min, float $max, float $default) : float{
        if(!is_numeric($value) || !is_finite((float) $value)){
            return $default;
        }
        return max($min, min($max, (float) $value));
    }
}
