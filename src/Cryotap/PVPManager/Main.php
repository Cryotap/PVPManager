<?php

namespace Cryotap\PVPManager;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\world\Position;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\entity\Human;
use pocketmine\Server;
use pocketmine\item\ItemBlock;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\item\ItemIds;
use pocketmine\event\server\CommandEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {

    public $inCombat = [];
	private $combatTimeout = 10;
    private $allowUseBed = true; // Default value, can be changed in config
	private $lastJoinTimes = true;
    private $allowFlying = true; // Default value, can be changed in config
    private $allowPVP = true; // Default value, can be changed in config
	private $allowSoupHeal = true; // Default value, can be changed in config
	private $allowLiquid = true; // Default value, can be changed in config

    public function onEnable(): void {
		$combatTimerTask = new CombatTimerTask($this);
		$this->getScheduler()->scheduleRepeatingTask($combatTimerTask, 20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        // Generate config.yml if it doesn't exist
    $this->saveDefaultConfig();
	$this->reloadConfig();
    // Load config settings
	$this->loadConfigValues();
    $this->combatTimeout = (int) $this->getConfig()->get("combat_timeout", 10);
    }
	
	private function loadConfigValues(): void {
    $this->reloadConfig();
    $this->allowUseBed = (bool)$this->getConfig()->get("allow_use_bed", true);
    $this->allowFlying = (bool)$this->getConfig()->get("allow_flying", true);
    $this->allowPVP = (bool)$this->getConfig()->get("allow_pvp", true);
    $this->allowSoupHeal = (bool)$this->getConfig()->get("allow_soup_heal", true);
    $this->allowLiquid = (bool)$this->getConfig()->get("allow_liquid", true);
}
	
	public function getInCombat(): array {
        return $this->inCombat;
    }


    public function onDisable(): void {
        $this->getLogger()->info(TF::RED . "PvPAdminCommands disabled!");
    }
	
	private function getPlayerLastJoinTime(Player $player): int {
        return $this->lastJoinTimes[strtolower($player->getName())] ?? 0;
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
    $entity = $event->getEntity();

    // Check if the entity being damaged is a player
		if ($entity instanceof Player) {
        $player = $entity;
        $playerName = $player->getName();
            // Cancel the damage from other entities during combat
            if ($event instanceof EntityDamageByEntityEvent) {
				$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
				if ($this->allowPVP == false) {
					if (in_array($player->getWorld()->getFolderName(), $noPVP)) {
						$target = $event->getDamager();
						if ($target instanceof Player) {
					$player->sendMessage(TF::RED . "PVP is disabled on this world.");
					$event->cancel(true);
						} else {
							$event->cancel();
						}
					} 
				} else {
					// Update the combat timer for the player
					$this->inCombat[$playerName] = time() + $this->combatTimeout;
					$player->sendMessage($this->combatTimeout);
				}
			}
		}
	}

    public function onBedEnter(PlayerBedEnterEvent $event) {
        $player = $event->getPlayer();
		if (isset($this->inCombat[$player->getName()])) {
			if ($this->allowUseBed == false) {
				$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
				if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
				// Cancel the bed enter event if using a bed is not allowed
				$event->cancel(true);
				$player->sendMessage(TF::RED . "Using a bed is currently disabled while logged.");
				} else {
					$event->cancel(true);
					$player->sendMessage(TF::RED . "Using a bed is currently disabled while logged.");
				}
			}
		}
    }

public function onEmpty(PlayerBucketEmptyEvent $event) {
    $player = $event->getPlayer();
    // Check if the item being placed is lava, flowing lava, or a lava bucket
	$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
        if ($this->allowLiquid === false && !in_array($player->getWorld()->getFolderName(), $noPVP)) {
                $player->sendMessage(TF::RED . "Placing Liquid/Lava is disabled here.");
                $event->cancel(); // Cancel the event to prevent the lava from being placed.
				return true;
		} 
}

	public function onItemConsume(PlayerItemConsumeEvent $event): void {
    $player = $event->getPlayer();
    $item = $event->getItem();
	var_dump($item->getName());
    // Check if the consumed item is soup
    if ($item->getName() === "Mushroom Stew") {
		if ($this->allowSoupHeal == true) {
			$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
			if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
        // Set the player's health to the maximum value
        $player->setHealth($player->getMaxHealth());
		$player->sendMessage(TF::GREEN . "Health restored.");
			}
		}
    }
}

    public function onToggleFlight(PlayerToggleFlightEvent $event) {
        $player = $event->getPlayer();
		if (isset($this->inCombat[$player->getName()])) {
			if ($this->allowFlying == false) {
				$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
				if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
				// Cancel the flight event if flying is not allowed
				$event->cancel(true);
				$player->sendMessage(TF::RED . "Flying is currently disabled while logged.");
				}
			}
		}
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
    if ($command->getName() === "pvpm") {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "This command can only be used in-game.");
            return true;
        }

        if (empty($args)) {
            $sender->sendMessage(TF::RED . "Usage: /pvpm editor");
            return true;
        }

        if ($args[0] === "editor") {
            if (!$sender->hasPermission("pvp.editor")) {
                $sender->sendMessage("You don't have permission to use the PvP editor.");
                return true;
            }
			$this->getConfig()->reload();
            $this->openPvPEditorForm($sender);
            return true;
        } else {
            $sender->sendMessage(TF::RED . "Usage: /pvpm editor");
            return true;
        }
    }
    return false;
}

    private function openPvPEditorForm(Player $player): void {
    $form = new SimpleForm(function (Player $player, ?int $data) {
        if ($data === null) {
            return;
        }

        // Check which button was tapped and toggle the corresponding setting
        switch ($data) {
            case 0:
                $this->toggleConfigSetting("allow_use_bed", "Use Bed");
                break;
            case 1:
                $this->toggleConfigSetting("allow_flying", "Flying");
                break;
            case 2:
                $this->toggleConfigSetting("allow_pvp", "Allow PVP");
                break;
            case 3:
                $this->toggleConfigSetting("allow_soup_heal", "Allow Soup Heal");
                break;
            case 4:
                $this->toggleConfigSetting("allow_liquid", "Allow Liquid");
                break;
        }

        // After toggling, update the button text to refresh the form
        $this->openPvPEditorForm($player);
    });

    $form->setTitle("PvP Editor");
    $form->setContent("Toggle PvP features:");

    // Set default values based on current config
    $form->addButton("Use Bed Toggle");
    $form->addButton("Flying Toggle");
    $form->addButton("Allow PVP Toggle");
    $form->addButton("Allow Soup Heal Toggle");
    $form->addButton("Allow Liquid/Lava Toggle");

    $form->sendToPlayer($player);
}

private function toggleConfigSetting(string $configKey, string $displayName): void {
    $currentValue = $this->getConfig()->get($configKey);
    $newValue = !$currentValue; // Toggle the value
    $this->getConfig()->set($configKey, $newValue);
    $this->saveConfig();
	$this->loadConfigValues();
    $status = $newValue ? "enabled" : "disabled";
    $this->getServer()->broadcastMessage(TF::PURPLE . "$displayName is now $status.");
}

    private function isInCombat(string $playerName): bool {
        return isset($this->inCombat[$playerName]) && time() < $this->inCombat[$playerName];
    }
}
