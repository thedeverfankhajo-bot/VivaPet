<?php

declare(strict_types=1);

namespace VivaPet\listener;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\scheduler\ClosureTask;
use VivaPet\Main;
use VivaPet\entity\PetEntity;

final class PlayerListener implements Listener{

    public function __construct(private readonly Main $plugin){}

    public function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void{
            if($player->isOnline()){
                $this->plugin->getPetManager()->handleJoin($player);
            }
        }), 1);
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $this->plugin->getPetManager()->removeForPlayer($event->getPlayer());
    }

    public function onDeath(PlayerDeathEvent $event) : void{
        $this->plugin->getPetManager()->removeForPlayer($event->getPlayer());
    }

    public function onRespawn(PlayerRespawnEvent $event) : void{
        $player = $event->getPlayer();
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void{
            if($player->isOnline()){
                $this->plugin->getPetManager()->handleJoin($player);
            }
        }), 2);
    }

    public function onPetDamage(EntityDamageEvent $event) : void{
        if($event->getEntity() instanceof PetEntity){
            $event->cancel();
        }
    }
}
