<?php
namespace icontrolu;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityMoveEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class iControlU extends PluginBase implements CommandExecutor, Listener{
    public $b;
    /** @var  ControlSession[] */
    public $s;
    /** @var  InventoryUpdateTask */
    public $inv;
    public function onEnable(){
        $this->s = [];
        $this->b = [];
        $this->inv = new InventoryUpdateTask($this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->inv, 5);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        if($sender instanceof Player){
            if(isset($args[0])){
                switch($args[0]){
                    case 'stop':
                    case 's':
                        if($this->isControl($sender)){
                            $this->s[$sender->getName()]->stopControl();
                            unset($this->b[$this->s[$sender->getName()]->getTarget()->getName()]);
                            unset($this->s[$sender->getName()]);
                            $sender->sendMessage("§aControl stopped. You have invisibility for 10 seconds.");
                            return true;
                        }
                        else{
                            $sender->sendMessage("§2You are not controlling anyone.");
                        }
                        break;
                    case 'control':
                    case 'c':
                        if(isset($args[1])){
                            if(($p = $this->getServer()->getPlayer($args[1])) instanceof Player){
                                if($p->isOnline()){
                                    if(isset($this->s[$p->getName()]) || isset($this->b[$p->getName()])){
                                        $sender->sendMessage("§bYou are already bound to a control session.");
                                        return true;
                                    }
                                    else{
                                        if($p->hasPermission("icu.exempt") || $p->getName() === $sender->getName()){
                                            $sender->sendMessage("&2You can't control this player.");
                                            return true;

                                        }
                                        else{
                                            $this->s[$sender->getName()] = new ControlSession($sender, $p, $this);
                                            $this->b[$p->getName()] = true;
                                            $sender->sendMessage("§aYou are now controlling§b " . $p->getName());
                                            return true;
                                        }
                                    }
                                }
                                else{
                                    $sender->sendMessage("§2Player not online.");
                                    return true;
                                }
                            }
                            else{
                                $sender->sendMessage("§2Player not found.");
                                return true;
                            }
                        }
                        break;
                    default:
                        return false;
                        break;
                }
            }
        }
        else{
            $sender->sendMessage("§2Please run command in game.");
            return true;
        }
    }
    public function onMove(PlayerMoveEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
        elseif($this->isControl($event->getPlayer())){
            $this->s[$event->getPlayer()->getName()]->updatePosition();
        }
    }
    public function onMessage(PlayerChatEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
        elseif($this->isControl($event->getPlayer())){
            $this->s[$event->getPlayer()->getName()]->sendChat($event);
            $event->setCancelled();
        }
    }
    public function onItemDrop(PlayerDropItemEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
    }
    public function onItemPickup(InventoryPickupItemEvent $event){
        if($event->getInventory()->getHolder() instanceof Player){
            if($this->isBarred($event->getInventory()->getHolder())){
                $event->setCancelled();
            }
        }
    }
    public function onBreak(BlockBreakEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
    }
    public function onPlace(BlockPlaceEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
    }
    public function onQuit(PlayerQuitEvent $event){
        if($this->isControl($event->getPlayer())){
            unset($this->b[$this->s[$event->getPlayer()->getName()]->getTarget()->getName()]);
            unset($this->s[$event->getPlayer()->getName()]);
        }
        elseif($this->isBarred($event->getPlayer())){
            foreach($this->s as $i){
                if($i->getTarget()->getName() == $event->getPlayer()->getName()){
                    $i->getControl()->sendMessage($event->getPlayer()->getName() . " §chas left the game. Your session has been closed.");
                    foreach($this->getServer()->getOnlinePlayers() as $online){
                        $online->showPlayer($i->getControl());
                    }
                    $i->getControl()->showPlayer($i->getTarget()); //Will work if my PR is merged

                    unset($this->b[$event->getPlayer()->getName()]);
                    unset($this->s[$i->getControl()->getName()]);
                    break;
                }
            }
        }
    }
    public function onPlayerAnimation(PlayerAnimationEvent $event){
        if($this->isBarred($event->getPlayer())){
            $event->setCancelled();
        }
        elseif($this->isControl($event->getPlayer())){
            $event->setCancelled();
            $pk = new AnimatePacket();
            $pk->eid = $this->s[$event->getPlayer()->getName()]->getTarget()->getID();
            $pk->action = $event->getAnimationType();
            $this->getServer()->broadcastPacket($this->s[$event->getPlayer()->getName()]->getTarget()->getViewers(), $pk);
        }
    }
    public function onDisable(){
        $this->getLogger()->info("Sessions are closing...");
        foreach($this->s as $i){
            $i->getControl()->sendMessage("iCU is disabling, you are visible.");
            foreach($this->getServer()->getOnlinePlayers() as $online){
                $online->showPlayer($i->getControl());
            }
            $i->getControl()->showPlayer($i->getTarget());
            unset($this->b[$i->getTarget()->getName()]);
            unset($this->s[$i->getControl()->getName()]);
        }
    }
    public function isControl(Player $p){
        return (isset($this->s[$p->getName()]));
    }
    public function isBarred(Player $p){
        return (isset($this->b[$p->getName()]));
    }
}
