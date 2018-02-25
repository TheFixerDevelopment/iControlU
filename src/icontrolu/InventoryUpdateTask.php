<?php
namespace icontrolu;

use pocketmine\scheduler\PluginTask;

class InventoryUpdateTask extends PluginTask{
    public function onRun(int $tick){
        /** @var iControlU $owner */
        $owner = $this->getOwner();
        foreach($owner->s as $session){
            $session->syncInventory();
        }
    }
}
