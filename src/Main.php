<?php

declare(strict_types=1);

namespace VivaPet;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use VivaPet\listener\PlayerListener;

final class Main extends PluginBase{

    private PetManager $petManager;
    private ?TaskHandler $followTask = null;

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->petManager = new PetManager($this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener($this), $this);
        $this->restartFollowTask();
        $this->getLogger()->info("VivaPet 2.0.0 enabled (vanilla Bedrock models; no resource pack).");
    }

    protected function onDisable() : void{
        if($this->followTask !== null){
            $this->followTask->cancel();
            $this->followTask = null;
        }
        if(isset($this->petManager)){
            $this->petManager->shutdown();
        }
    }

    public function getPetManager() : PetManager{
        return $this->petManager;
    }

    public function restartFollowTask() : void{
        if($this->followTask !== null){
            $this->followTask->cancel();
        }
        $value = $this->getConfig()->getNested("settings.follow-interval-ticks", 2);
        $interval = is_numeric($value) ? max(1, min(10, (int) $value)) : 2;
        $this->followTask = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            $this->petManager->tick();
        }), $interval);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(strtolower($command->getName()) !== "pet"){
            return false;
        }
        if(!$sender->hasPermission("vivapet.command")){
            $sender->sendMessage("§cYour rank does not have access to pet commands.");
            return true;
        }

        $sub = strtolower((string) ($args[0] ?? "help"));
        if($sub === "reload"){
            if(!$sender->hasPermission("vivapet.admin")){
                $sender->sendMessage("§cYou need vivapet.admin.");
                return true;
            }
            $this->reloadConfig();
            $this->restartFollowTask();
            $sender->sendMessage("§aVivaPet configuration reloaded.");
            return true;
        }

        if(!$sender instanceof Player){
            $sender->sendMessage("This command must be run in-game.");
            return true;
        }

        switch($sub){
            case "help":
                $this->sendHelp($sender);
                break;

            case "list":
                $page = isset($args[1]) && ctype_digit((string) $args[1]) ? (int) $args[1] : 1;
                $this->sendPetList($sender, max(1, $page));
                break;

            case "summon":
                $petId = strtolower((string) ($args[1] ?? ""));
                if($petId === ""){
                    $sender->sendMessage("§eUsage: /pet summon <pet>");
                    return true;
                }
                $this->sendSummonResult($sender, $this->petManager->summon($sender, $petId));
                break;

            case "random":
                $this->sendSummonResult($sender, $this->petManager->summonRandom($sender));
                break;

            case "hide":
                if($this->petManager->hide($sender)){
                    $sender->sendMessage("§eYour companion is hidden. Use §f/pet show §eto bring it back.");
                }else{
                    $sender->sendMessage("§cYou do not have an active companion.");
                }
                break;

            case "show":
                $this->sendShowResult($sender, $this->petManager->showSelected($sender));
                break;

            case "toggle":
                if($this->petManager->getActivePetId($sender) !== null){
                    $this->petManager->hide($sender);
                    $sender->sendMessage("§eYour companion is hidden. Use §f/pet show §eto bring it back.");
                }else{
                    $this->sendShowResult($sender, $this->petManager->showSelected($sender));
                }
                break;

            case "active":
            case "status":
                $activePetId = $this->petManager->getActivePetId($sender);
                $selectedPetId = $this->petManager->getSelectedPetId($sender);
                $petId = $activePetId ?? $selectedPetId;
                if($petId === null){
                    $sender->sendMessage("§7You have no selected companion. Use §f/pet list§7.");
                    break;
                }
                $definition = $this->petManager->getPetDefinition($petId);
                $name = is_array($definition) && is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);
                if($activePetId !== null){
                    $sender->sendMessage("§aActive companion: §f" . $name . " §7(" . $petId . ")");
                }elseif(!$this->petManager->hasPetPermission($sender, $petId)){
                    $sender->sendMessage("§cYour saved companion is locked by your current rank: §f" . $name);
                    $sender->sendMessage("§7The selection remains saved and works again when the permission is restored.");
                }else{
                    $sender->sendMessage("§eSelected companion is hidden: §f" . $name . " §7(" . $petId . ")");
                    $sender->sendMessage("§7Use §f/pet show §7to spawn it again.");
                }
                break;

            case "remove":
            case "despawn":
                $hadSelection = $this->petManager->getSelectedPetId($sender) !== null;
                $wasActive = $this->petManager->getActivePetId($sender) !== null;
                $this->petManager->remove($sender, true);
                $sender->sendMessage($hadSelection || $wasActive ? "§eYour companion was removed and its selection was cleared." : "§7You did not have a selected companion.");
                break;

            case "info":
                $this->sendPetInfo($sender, strtolower((string) ($args[1] ?? "")));
                break;

            default:
                $sender->sendMessage("§cUnknown subcommand. Use §f/pet help§c.");
                break;
        }
        return true;
    }

    private function sendHelp(Player $player) : void{
        $player->sendMessage("§6───── §eVivaPet §6─────");
        $player->sendMessage("§f/pet list [page] §7- Browse pets and rank locks");
        $player->sendMessage("§f/pet summon <pet> §7- Summon one unlocked pet");
        $player->sendMessage("§f/pet random §7- Summon a random pet unlocked by your rank");
        $player->sendMessage("§f/pet hide|show|toggle §7- Control your companion");
        $player->sendMessage("§f/pet active §7- Show your current selection");
        $player->sendMessage("§f/pet info <pet> §7- View effects and model details");
        $player->sendMessage("§f/pet remove §7- Despawn and forget your selection");
    }

    private function sendSummonResult(Player $player, string $result) : void{
        switch($result){
            case "ok":
                $player->sendMessage("§aYour companion has been summoned. Its effects are now active.");
                break;
            case "denied":
                $player->sendMessage("§cYour rank does not unlock this pet.");
                break;
            case "unknown":
                $player->sendMessage("§cUnknown, disabled or unavailable pet.");
                break;
            case "none":
                $player->sendMessage("§cYour rank does not unlock any configured pets yet.");
                break;
            default:
                $player->sendMessage("§cThe pet could not be summoned.");
                break;
        }
    }

    private function sendShowResult(Player $player, string $result) : void{
        switch($result){
            case "ok":
                $player->sendMessage("§aYour selected companion has been summoned.");
                break;
            case "denied":
                $player->sendMessage("§cYour current rank does not unlock the selected pet.");
                break;
            case "none":
                $player->sendMessage("§7You have no saved pet selection. Use §f/pet list§7 and summon one.");
                break;
            case "unknown":
                $player->sendMessage("§cYour saved pet is no longer configured. Choose another with /pet list.");
                break;
            default:
                $player->sendMessage("§cThe selected pet could not be summoned.");
                break;
        }
    }

    private function sendPetList(Player $player, int $page) : void{
        $ids = $this->petManager->getPetIds();
        $perPage = 12;
        $pageCount = max(1, (int) ceil(count($ids) / $perPage));
        $page = min($page, $pageCount);
        $activePetId = $this->petManager->getActivePetId($player);
        $selectedPetId = $this->petManager->getSelectedPetId($player);
        $player->sendMessage("§6──── §eVivaPet companions §7(Page " . $page . "/" . $pageCount . ") §6────");
        foreach(array_slice($ids, ($page - 1) * $perPage, $perPage) as $petId){
            $definition = $this->petManager->getPetDefinition($petId);
            if($definition === null){
                continue;
            }
            $name = is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);
            if(!$this->petManager->hasPetPermission($player, $petId)){
                $status = "§cRank required";
            }elseif($activePetId === $petId){
                $status = "§aFollowing you";
            }elseif($selectedPetId === $petId){
                $status = "§eSelected (hidden)";
            }else{
                $status = "§aUnlocked";
            }
            $player->sendMessage("§7• " . $name . " §8(" . $petId . ") §7- " . $status);
        }
        $player->sendMessage("§7Summon: §f/pet summon <pet> §8| §7Random: §f/pet random");
        if($page < $pageCount){
            $player->sendMessage("§7Next page: §f/pet list " . ($page + 1));
        }
        if($page > 1){
            $player->sendMessage("§7Previous page: §f/pet list " . ($page - 1));
        }
    }

    private function sendPetInfo(Player $player, string $petId) : void{
        $definition = $this->petManager->getPetDefinition($petId);
        if($definition === null){
            $player->sendMessage("§cUnknown pet. Use /pet list.");
            return;
        }
        $name = is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);
        $description = is_string($definition["description"] ?? null) ? $definition["description"] : "No description.";
        $access = $this->petManager->hasPetPermission($player, $petId) ? "§aUnlocked" : "§cLocked by rank";
        $permission = $definition["permission"] ?? ("vivapet.pet." . $petId);
        if(!is_string($permission) || !preg_match('/^[a-zA-Z0-9._-]{1,96}$/D', $permission)){
            $permission = "invalid configuration";
        }
        $player->sendMessage("§6" . $name . " §7- " . $access);
        $player->sendMessage("§7" . $description);
        $player->sendMessage("§7Permission: §f" . $permission);
        $effects = $definition["effects"] ?? [];
        if(is_array($effects) && $effects !== []){
            $player->sendMessage("§7Effects: §f" . implode(", ", array_keys($effects)));
        }
        $player->sendMessage("§7Model: §f" . self::getModelDescription($petId));
        $player->sendMessage("§7Summon: §f/pet summon " . $petId);
    }

    private static function getModelDescription(string $petId) : string{
        $proxyModels = [
            "lion" => "cat model (vanilla proxy)", "tiger" => "cat model (vanilla proxy)",
            "leopard" => "cat model (vanilla proxy)", "red_panda" => "panda model (vanilla proxy)",
            "raccoon" => "fox model (vanilla proxy)", "deer" => "goat model (vanilla proxy)",
            "owl" => "parrot model (vanilla proxy)", "eagle" => "parrot model (vanilla proxy)",
            "crow" => "parrot model (vanilla proxy)", "phoenix" => "parrot model (vanilla proxy)",
            "shark" => "dolphin model (vanilla proxy)", "whale" => "dolphin model (vanilla proxy)",
            "unicorn" => "horse model (vanilla proxy)", "griffin" => "horse model (vanilla proxy)",
            "pegasus" => "phantom model (vanilla proxy)", "monkey" => "fox model (vanilla proxy)",
            "hedgehog" => "armadillo model (vanilla proxy)", "dinosaur" => "sniffer model (vanilla proxy)",
            "penguin" => "chicken model (vanilla proxy)", "husky" => "wolf model",
            "arctic_fox" => "fox model", "bunny" => "rabbit model", "sea_turtle" => "turtle model"
        ];
        return $proxyModels[$petId] ?? "vanilla Bedrock model";
    }
}
