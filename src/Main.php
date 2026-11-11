<?php

declare(strict_types=1);

namespace VivaPet;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use VivaPet\listener\PlayerListener;

final class Main extends PluginBase{

    private PetManager $petManager;

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->petManager = new PetManager($this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener($this), $this);
        $this->installResourcePack();

        $intervalValue = $this->getConfig()->getNested("settings.follow-interval-ticks", 2);
        $interval = is_numeric($intervalValue) ? max(1, min(10, (int) $intervalValue)) : 2;
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            $this->petManager->tick();
        }), $interval);

        $this->getLogger()->info("VivaPet 1.1.0 enabled.");
    }

    protected function onDisable() : void{
        if(isset($this->petManager)){
            $this->petManager->shutdown();
        }
    }

    public function getPetManager() : PetManager{ return $this->petManager; }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(strtolower($command->getName()) !== "pet"){
            return false;
        }
        if(!$sender->hasPermission("vivapet.command")){
            $sender->sendMessage("§cYou don't have permission to use this command.");
            return true;
        }

        $sub = strtolower((string) ($args[0] ?? "help"));
        if($sub === "reload"){
            if(!$sender->hasPermission("vivapet.admin")){
                $sender->sendMessage("§cYou need vivapet.admin.");
                return true;
            }
            $this->reloadConfig();
            $sender->sendMessage("§aVivaPet config reloaded. Pack changes require a full restart.");
            return true;
        }

        if(!$sender instanceof \pocketmine\player\Player){
            $sender->sendMessage("This command must be run in-game.");
            return true;
        }

        switch($sub){
            case "help":
                $sender->sendMessage("§6VivaPet §7» §f/pet list");
                $sender->sendMessage("§7/pet summon <pet> §8- §fSummon a companion");
                $sender->sendMessage("§7/pet hide §8- §fHide your companion temporarily");
                $sender->sendMessage("§7/pet show §8- §fSpawn your selected companion");
                $sender->sendMessage("§7/pet toggle §8- §fHide or show your selected companion");
                $sender->sendMessage("§7/pet active §8- §fView your selected/active companion");
                $sender->sendMessage("§7/pet remove §8- §fRemove your companion and forget the selection");
                $sender->sendMessage("§7/pet info <pet> §8- §fView pet details");
                break;

            case "list":
                $this->sendPetList($sender);
                break;

            case "summon":
                $petId = strtolower((string) ($args[1] ?? ""));
                if($petId === ""){
                    $sender->sendMessage("§eUsage: /pet summon <pet>");
                    return true;
                }
                $result = $this->petManager->summon($sender, $petId);
                switch($result){
                    case "ok":
                        $sender->sendMessage("§aYour companion has been summoned.");
                        break;
                    case "denied":
                        $sender->sendMessage("§cYour rank does not unlock this pet.");
                        break;
                    case "unknown":
                        $sender->sendMessage("§cUnknown or disabled pet.");
                        break;
                    default:
                        $sender->sendMessage("§cThe pet could not be summoned.");
                        break;
                }
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
                    $sender->sendMessage("§cYour saved companion is currently locked by your rank: §f" . $name);
                    $sender->sendMessage("§7The saved choice will be available again when the required permission is restored.");
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
                if($hadSelection || $wasActive){
                    $sender->sendMessage("§eYour companion was removed and its selection was cleared.");
                }else{
                    $sender->sendMessage("§7You did not have a selected companion.");
                }
                break;

            case "info":
                $petId = strtolower((string) ($args[1] ?? ""));
                $definition = $this->petManager->getPetDefinition($petId);
                if($definition === null){
                    $sender->sendMessage("§cUnknown pet.");
                    return true;
                }
                $name = is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);
                $description = is_string($definition["description"] ?? null) ? $definition["description"] : "No description.";
                $access = $this->petManager->hasPetPermission($sender, $petId) ? "§aUnlocked" : "§cLocked";
                $permission = $definition["permission"] ?? ("vivapet.pet." . $petId);
                if(!is_string($permission) || !preg_match('/^[a-zA-Z0-9._-]{1,96}$/D', $permission)){
                    $permission = "invalid configuration";
                }
                $sender->sendMessage("§6" . $name . " §7- " . $access);
                $sender->sendMessage("§7" . $description);
                $sender->sendMessage("§7Required permission: §f" . $permission);
                break;

            default:
                $sender->sendMessage("§cUnknown subcommand. Use /pet.");
                break;
        }
        return true;
    }

    private function sendShowResult(\pocketmine\player\Player $player, string $result) : void{
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
                $player->sendMessage("§cYour saved pet is no longer configured. Choose another pet with /pet list.");
                break;
            default:
                $player->sendMessage("§cThe selected pet could not be summoned.");
                break;
        }
    }

    private function sendPetList(\pocketmine\player\Player $player) : void{
        $player->sendMessage("§6───── §eVivaPet companions §6─────");
        $activePetId = $this->petManager->getActivePetId($player);
        $selectedPetId = $this->petManager->getSelectedPetId($player);
        foreach($this->petManager->getPetIds() as $petId){
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
        $player->sendMessage("§7Summon: §f/pet summon <pet> §8| §7Hide: §f/pet hide §8| §7Show: §f/pet show");
        $player->sendMessage("§7Clear your saved choice with: §f/pet remove");
    }

    private function installResourcePack() : void{
        if(!(bool) $this->getConfig()->getNested("settings.resource-pack.auto-install", true)){
            return;
        }

        $filename = "VivaPet.mcpack";
        $this->saveResource($filename, true);
        $source = $this->getDataFolder() . $filename;
        $packDirectory = $this->getServer()->getDataPath() . "resource_packs/";

        if(!is_file($source)){
            $this->getLogger()->warning("Bundled resource pack not found: " . $source);
            return;
        }
        if(!is_dir($packDirectory) && !@mkdir($packDirectory, 0775, true) && !is_dir($packDirectory)){
            $this->getLogger()->warning("Could not create resource_packs directory.");
            return;
        }

        $target = $packDirectory . $filename;
        $sourceHash = @hash_file("sha256", $source);
        $targetHash = is_file($target) ? @hash_file("sha256", $target) : false;
        $packChanged = !is_string($sourceHash) || !is_string($targetHash) || !hash_equals($sourceHash, $targetHash);
        if($packChanged && !@copy($source, $target)){
            $this->getLogger()->warning("Could not copy resource pack; check server directory permissions.");
            return;
        }

        $packsConfig = new Config($packDirectory . "resource_packs.yml", Config::YAML, ["resource_stack" => []]);
        $stack = $packsConfig->get("resource_stack", []);
        if(!is_array($stack)){
            $stack = [];
        }
        $stack = array_values(array_filter($stack, "is_string"));
        $stackChanged = false;
        if(!in_array($filename, $stack, true)){
            array_unshift($stack, $filename);
            $packsConfig->set("resource_stack", $stack);
            $packsConfig->save();
            $stackChanged = true;
        }

        if($packChanged || $stackChanged){
            $this->getLogger()->notice("VivaPet resource pack installed/updated. Fully restart the server to load it.");
        }
    }
}
