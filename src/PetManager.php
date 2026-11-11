<?php

declare(strict_types=1);

namespace VivaPet;

require_once __DIR__ . "/entity/PetTypes.php";

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\SmokeParticle;
use VivaPet\entity\PetEntity;
use VivaPet\entity\CatPet;
use VivaPet\entity\WolfPet;
use VivaPet\entity\FoxPet;
use VivaPet\entity\RabbitPet;
use VivaPet\entity\ParrotPet;
use VivaPet\entity\BatPet;
use VivaPet\entity\BeePet;
use VivaPet\entity\PandaPet;
use VivaPet\entity\AxolotlPet;
use VivaPet\entity\TurtlePet;
use VivaPet\entity\DolphinPet;
use VivaPet\entity\FrogPet;
use VivaPet\entity\GoatPet;
use VivaPet\entity\HorsePet;
use VivaPet\entity\CamelPet;
use VivaPet\entity\SnifferPet;
use VivaPet\entity\AllayPet;
use VivaPet\entity\ArmadilloPet;
use VivaPet\entity\ChickenPet;
use VivaPet\entity\CowPet;
use VivaPet\entity\PigPet;
use VivaPet\entity\SheepPet;
use VivaPet\entity\OcelotPet;
use VivaPet\entity\MooshroomPet;
use VivaPet\entity\PolarBearPet;
use VivaPet\entity\LlamaPet;
use VivaPet\entity\SquidPet;
use VivaPet\entity\DragonPet;
use VivaPet\entity\SlimePet;
use VivaPet\entity\PhantomPet;
use VivaPet\entity\BlazePet;
use VivaPet\entity\GhastPet;
use VivaPet\entity\SnowGolemPet;
use VivaPet\entity\PufferfishPet;
use VivaPet\entity\CodPet;
use VivaPet\entity\SalmonPet;
use VivaPet\entity\TropicalFishPet;
use VivaPet\entity\TadpolePet;
use VivaPet\entity\StriderPet;
use VivaPet\entity\DonkeyPet;
use VivaPet\entity\MulePet;
use VivaPet\entity\SkeletonHorsePet;
use VivaPet\entity\ZombieHorsePet;

final class PetManager{

    /** @var array<string, class-string<PetEntity>> */
    private const ENTITY_CLASSES = [
        "fox" => FoxPet::class,
        "arctic_fox" => FoxPet::class,
        "raccoon" => FoxPet::class,
        "wolf" => WolfPet::class,
        "husky" => WolfPet::class,
        "cat" => CatPet::class,
        "lion" => CatPet::class,
        "tiger" => CatPet::class,
        "leopard" => CatPet::class,
        "rabbit" => RabbitPet::class,
        "bunny" => RabbitPet::class,
        "parrot" => ParrotPet::class,
        "owl" => ParrotPet::class,
        "eagle" => ParrotPet::class,
        "crow" => ParrotPet::class,
        "phoenix" => ParrotPet::class,
        "bat" => BatPet::class,
        "bee" => BeePet::class,
        "panda" => PandaPet::class,
        "red_panda" => PandaPet::class,
        "axolotl" => AxolotlPet::class,
        "turtle" => TurtlePet::class,
        "sea_turtle" => TurtlePet::class,
        "dolphin" => DolphinPet::class,
        "shark" => DolphinPet::class,
        "whale" => DolphinPet::class,
        "frog" => FrogPet::class,
        "goat" => GoatPet::class,
        "deer" => GoatPet::class,
        "horse" => HorsePet::class,
        "unicorn" => HorsePet::class,
        "griffin" => HorsePet::class,
        "pegasus" => PhantomPet::class,
        "camel" => CamelPet::class,
        "sniffer" => SnifferPet::class,
        "dinosaur" => SnifferPet::class,
        "allay" => AllayPet::class,
        "armadillo" => ArmadilloPet::class,
        "hedgehog" => ArmadilloPet::class,
        "chicken" => ChickenPet::class,
        "penguin" => ChickenPet::class,
        "cow" => CowPet::class,
        "pig" => PigPet::class,
        "sheep" => SheepPet::class,
        "ocelot" => OcelotPet::class,
        "mooshroom" => MooshroomPet::class,
        "polar_bear" => PolarBearPet::class,
        "llama" => LlamaPet::class,
        "squid" => SquidPet::class,
        "dragon" => DragonPet::class,
        "slime" => SlimePet::class,
        "phantom" => PhantomPet::class,
        "blaze" => BlazePet::class,
        "ghast" => GhastPet::class,
        "snow_golem" => SnowGolemPet::class,
        "pufferfish" => PufferfishPet::class,
        "cod" => CodPet::class,
        "salmon" => SalmonPet::class,
        "tropical_fish" => TropicalFishPet::class,
        "tadpole" => TadpolePet::class,
        "strider" => StriderPet::class,
        "donkey" => DonkeyPet::class,
        "mule" => MulePet::class,
        "skeleton_horse" => SkeletonHorsePet::class,
        "zombie_horse" => ZombieHorsePet::class
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
        "absorption" => [VanillaEffects::class, "ABSORPTION"],
        "health_boost" => [VanillaEffects::class, "HEALTH_BOOST"],
        "conduit_power" => [VanillaEffects::class, "CONDUIT_POWER"]
    ];

    /** @var array<string, class-string<Particle>> */
    private const AURAS = [
        "heart" => HeartParticle::class,
        "flame" => FlameParticle::class,
        "portal" => PortalParticle::class,
        "enchant" => EnchantmentTableParticle::class,
        "villager" => HappyVillagerParticle::class,
        "happy_villager" => HappyVillagerParticle::class,
        "smoke" => SmokeParticle::class,
        "bubble" => BubbleParticle::class
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
        $ids = [];
        foreach(array_keys($configured) as $id){
            if(is_string($id) && isset(self::ENTITY_CLASSES[$id]) && $this->getPetDefinition($id) !== null){
                $ids[] = $id;
            }
        }
        sort($ids, SORT_STRING);
        return $ids;
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

    /** @return "ok"|"none"|"unknown"|"denied"|"offline" */
    public function summonRandom(Player $player) : string{
        $unlocked = array_values(array_filter(
            $this->getPetIds(),
            fn(string $petId) : bool => $this->hasPetPermission($player, $petId)
        ));
        if($unlocked === []){
            return "none";
        }
        return $this->summon($player, $unlocked[random_int(0, count($unlocked) - 1)]);
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
        $scale = is_numeric($scaleValue) && is_finite((float) $scaleValue) ? max(0.20, min(2.0, (float) $scaleValue)) : 1.0;
        $name = $definition["name"] ?? ucfirst($petId);
        if(!is_string($name) || $name === ""){
            $name = ucfirst($petId);
        }
        $name = substr($name, 0, 128);

        try{
            $pet = new $class($this->getFollowLocation($player), $uuid, $petId, $name, $scale);
            $pet->setNameTagVisible((bool) ($definition["name-tag-visible"] ?? true));
            $pet->setNameTagAlwaysVisible((bool) ($definition["name-tag-visible"] ?? true));
            $visibility = strtolower((string) $this->plugin->getConfig()->getNested("settings.visibility", "all"));
            if($visibility === "owner"){
                $pet->spawnTo($player);
            }else{
                $pet->spawnToAll();
            }
        }catch(\Throwable $error){
            $this->plugin->getLogger()->warning("Could not create pet '" . $petId . "': " . $error->getMessage());
            return "unknown";
        }

        $this->activePets[$uuid] = ["player" => $player, "pet" => $pet, "petId" => $petId, "ticks" => 0];
        $this->playerData->set($uuid, $petId);
        $this->playerData->save();
        $this->applyPetEffects($player, $definition);
        return "ok";
    }

    /** Hides the active pet but keeps the saved selection. */
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
        $auraSteps = max(1, (int) ceil($this->boundedInt($settings["aura-interval-ticks"] ?? 10, 4, 40, 10) / $interval));

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
            if(($ticks % $auraSteps) === 0){
                $this->emitAura($player, $pet, $definition, $settings);
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
        $settings = is_array($settings) ? $settings : [];
        $duration = $this->boundedInt($settings["effect-duration-ticks"] ?? 100, 40, 1200, 100);
        $refreshTicks = $this->boundedInt($settings["effect-refresh-ticks"] ?? 40, 10, 200, 40);
        $followInterval = $this->boundedInt($settings["follow-interval-ticks"] ?? 2, 1, 10, 2);
        $duration = max($duration, $refreshTicks + $followInterval + 5);

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

    /** @param array<string, mixed> $definition @param array<string, mixed> $settings */
    private function emitAura(Player $player, PetEntity $pet, array $definition, array $settings) : void{
        $aura = $definition["aura"] ?? "none";
        if(!is_string($aura) || !isset(self::AURAS[$aura])){
            return;
        }
        $class = self::AURAS[$aura];
        $position = $pet->getPosition()->add(0.0, 0.65, 0.0);
        $visibility = strtolower((string) ($settings["visibility"] ?? "all"));
        $viewers = $visibility === "owner" ? [$player] : null;
        $player->getWorld()->addParticle($position, new $class(), $viewers);
    }

    private function getFollowLocation(Player $player) : Location{
        $position = $player->getPosition();
        $yaw = $player->getLocation()->getYaw();
        $radians = deg2rad($yaw);
        $settings = $this->plugin->getConfig()->get("settings", []);
        $settings = is_array($settings) ? $settings : [];
        $offset = $this->boundedFloat($settings["follow-offset"] ?? 1.55, 0.75, 3.5, 1.55);
        $heightOffset = $this->boundedFloat($settings["follow-height-offset"] ?? 0.05, -1.0, 2.0, 0.05);
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
