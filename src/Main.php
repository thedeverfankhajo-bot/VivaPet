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

        $this->getLogger()->info("VivaPet 1.0.0 enabled.");
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
                $sender->sendMessage("§7/pet remove §8- §fRemove your companion");
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

            case "remove":
            case "despawn":
                $this->petManager->remove($sender, true);
                $sender->sendMessage("§eYour companion has been removed.");
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
                $sender->sendMessage("§6" . $name . " §7- " . $access);
                $sender->sendMessage("§7" . $description);
                break;

            default:
                $sender->sendMessage("§cUnknown subcommand. Use /pet.");
                break;
        }
        return true;
    }

    private function sendPetList(\pocketmine\player\Player $player) : void{
        $player->sendMessage("§6───── §eVivaPet companions §6─────");
        foreach($this->petManager->getPetIds() as $petId){
            $definition = $this->petManager->getPetDefinition($petId);
            if($definition === null){
                continue;
            }
            $name = is_string($definition["name"] ?? null) ? $definition["name"] : ucfirst($petId);
            $status = $this->petManager->hasPetPermission($player, $petId) ? "§aUnlocked" : "§cRank required";
            $player->sendMessage("§7• " . $name . " §8(" . $petId . ") §7- " . $status);
        }
        $player->sendMessage("§7Summon one with: §f/pet summon <pet>");
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
