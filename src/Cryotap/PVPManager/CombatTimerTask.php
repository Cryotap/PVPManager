<?php

namespace Cryotap\PVPManager;

use pocketmine\scheduler\Task;

class CombatTimerTask extends Task {
    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void { // Make sure to pass $currentTick parameter here
        foreach ($this->plugin->getInCombat() as $playerName => $endTime) {
            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($player !== null) {
                $remainingTime = $endTime - time();
                if ($remainingTime <= 0) {
                    unset($this->plugin->inCombat[$playerName]); // You can still use unset on the protected property
                    $player->sendMessage("You are no longer in combat.");
                }
            } else {
                // Player is offline, remove from combat list
                unset($this->plugin->inCombat[$playerName]);
            }
        }
    }
}
