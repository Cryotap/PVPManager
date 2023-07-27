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
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerExhaustEvent;
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
use Vecnavium\FormsUI\SimpleForm;
use Vecnavium\FormsUI\CustomForm;

class Main extends PluginBase implements Listener {

    public $inCombat = [];
	private $combatTimeout = 10;
    private $allowUseBed = true; // Default value, can be changed in config
    private $allowFlying = true; // Default value, can be changed in config
    private $allowPVP = true; // Default value, can be changed in config
	private $allowSoupHeal = true; // Default value, can be changed in config
	private $allowLiquid = true; // Default value, can be changed in config
	private $allowCustomHealth = true; // Default value, can be changed in config
	private $allowExhaust = true; // Default value, can be changed in config
	private $allowCustomBaseDamage = true; // Default value, can be changed in config
	private $customBaseDamage = 6; // Default value, can be changed in config
	private $customHealth = 6; // Default value, can be changed in config
	private $soupHeal = 6; // Default value, can be changed in config

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
	$this->allowCustomHealth = (bool)$this->getConfig()->get("allow_custom_health", true);
	$this->allowExhaust = (bool)$this->getConfig()->get("allow_exhaust", true);
	$this->allowCustomBaseDamage = (bool)$this->getConfig()->get("allow_custom_base_damage", true);
	$this->customBaseDamage = (float)$this->getConfig()->get("custom_base_damage", true);
	$this->customHealth = (int)$this->getConfig()->get("custom_health", true);
	$this->soupHeal = (int)$this->getConfig()->get("soup_heal", true);
}
	
	public function getInCombat(): array {
        return $this->inCombat;
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
						if ($target instanceof Player && $player instanceof Player) {
					$target->sendMessage(TF::RED . "PVP is disabled on this world.");
					$event->cancel();
						}  
					}						
				} else {
					// Update the combat timer for the player
					$this->inCombat[$playerName] = time() + $this->combatTimeout;
					if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
						$target = $event->getDamager();
						if ($target instanceof Player) {
							if ($this->allowCustomBaseDamage == true) {
							$event->setBaseDamage($this->customBaseDamage);
							}
						}  
					}
				}
			} 
		}
	}
	
	public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
		$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
		if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
		if (isset($this->inCombat[$player->getName()])) {
			$this->setCustomHealth($player);
		}
		}
    }
	
	public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();
		$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
		if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
			if ($this->allowExhaust == false) {
				$event->cancel();
			}
		}
    }
	
	public function setCustomHealth($player): void {
		$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
		if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
		if ($this->allowCustomHealth == true) {
			$player->setMaxHealth($this->customHealth);
		} else {
			$player->setMaxHealth(20);
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
				$event->cancel();
				$player->sendMessage(TF::RED . "Using a bed is currently disabled while logged.");
				} else {
					$event->cancel();
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
    // Check if the consumed item is soup
    if ($item->getName() === "Mushroom Stew") {
		if ($this->allowSoupHeal == true) {
			$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
			if (!in_array($player->getWorld()->getFolderName(), $noPVP)) {
        // Set the player's health to the maximum value
        $player->setHealth($this->soupHeal);
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
				$event->cancel();
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
                $sender->sendMessage(TF::RED . "You don't have permission to use the PvP editor.");
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
			case 5:
                $this->toggleConfigSetting("allow_custom_health", "Allow Custom Health");
				$players = $this->getServer()->getOnlinePlayers();
                foreach ($players as $target) {
					$this->setCustomHealth($target);
				}
                break;
			case 6:
                $this->toggleConfigSetting("allow_exhaust", "Allow Exhaustion");
                break;
			case 7:
                $this->toggleConfigSetting("allow_custom_base_damage", "Allow Custom Damage");
                break;
			case 8:
				$this->openCustomHealthForm($player);
				break;
			case 9:
                $this->openSoupHealForm($player);
                break;
			case 10:
                $this->openCustomBaseDamageForm($player);
                break;
        }
    });

    $form->setTitle(TF::DARK_RED . "PvP Editor");
    $form->setContent(TF::RED . "Toggle PvP features:");

    // Set default values based on current config
    $form->addButton(TF::DARK_PURPLE . "Use Bed Toggle");
    $form->addButton(TF::DARK_PURPLE . "Flying Toggle");
    $form->addButton(TF::DARK_PURPLE . "Allow PVP Toggle");
    $form->addButton(TF::DARK_PURPLE . "Allow Soup Heal Toggle");
    $form->addButton(TF::DARK_PURPLE . "Allow Liquid/Lava Toggle");
	$form->addButton(TF::DARK_PURPLE . "Allow Custom Health Toggle");
	$form->addButton(TF::DARK_PURPLE . "Allow Exhaust Toggle");
	$form->addButton(TF::DARK_PURPLE . "Allow Custom Damage Toggle");
	$form->addButton(TF::DARK_PURPLE . "Set Custom Health");
	$form->addButton(TF::DARK_PURPLE . "Set Custom Soup Heal");
	$form->addButton(TF::DARK_PURPLE . "Set Custom Damage");

    $form->sendToPlayer($player);
}

private function openCustomHealthForm(Player $player): void {
    $form = new CustomForm(function (Player $player, ?array $data) {
        if ($data === null) {
            return;
        }

        // Check if the form was submitted with valid values
        if (isset($data[0]) && is_numeric($data[0])) {
            $newValue = (int) $data[0];
            // Validate the value (you can add more validation rules if needed)
                // Set the new value for custom_health in the config
                $this->getConfig()->set("custom_health", $newValue);
                $this->saveConfig();
				$this->loadConfigValues();
				$player->sendMessage(TF::GREEN . "Custom Health set to: " . $newValue);
				$players = $this->getServer()->getOnlinePlayers();
                foreach ($players as $target) {
				$noPVP = $this->getConfig()->get("pvp_disabled_worlds", []);
				if ($this->allowCustomHealth == true) {
                $player->setMaxHealth($newValue);
                $player->setHealth($newValue);
				}
				}
        }
    });

    $form->setTitle(TF::AQUA . "Set Custom Health");
    $form->addInput("Enter the Custom Health (1-100)", "", (string) $this->getConfig()->get("custom_health"));

    $form->sendToPlayer($player);
}

private function openSoupHealForm(Player $player): void {
    $form = new CustomForm(function (Player $player, ?array $data) {
        if ($data === null) {
            return;
        }

        // Check if the form was submitted with valid values
        if (isset($data[0]) && is_numeric($data[0])) {
            $newValue = (int) $data[0];
            // Validate the value (you can add more validation rules if needed)
            if ($newValue >= 1 && $newValue <= $player->getMaxHealth()) {
                // Set the new value for soup_heal in the config
                $this->getConfig()->set("soup_heal", $newValue);
                $this->saveConfig();
				$this->loadConfigValues();
                $player->sendMessage(TF::GREEN . "Soup Heal Amount set to: " . $newValue);
            } else {
                $player->sendMessage(TF::RED . "Invalid value! Soup Heal Amount must be between 1 and 20.");
            }
        }
    });

    $form->setTitle(TF::AQUA . "Set Soup Heal Amount");
    $form->addInput("Enter the Soup Heal Amount (Within Current Max)", "", (string) $this->getConfig()->get("soup_heal"));

    $form->sendToPlayer($player);
}

private function openCustomBaseDamageForm(Player $player): void {
    $form = new CustomForm(function (Player $player, ?array $data) {
        if ($data === null) {
            return;
        }

        // Check if the form was submitted with valid values
        if (isset($data[0]) && is_numeric($data[0])) {
            $newValue = (int) $data[0];
                // Set the new value for custom_base_damage in the config
                $this->getConfig()->set("custom_base_damage", $newValue);
                $this->saveConfig();
				$this->loadConfigValues();
                $player->sendMessage(TF::GREEN . "Custom Base Damage set to: " . $newValue);
        }
    });

    $form->setTitle(TF::AQUA . "Set Custom Base Damage");
    $form->addInput("Enter the Custom Base Damage (Within Current Max)", "", (string) $this->getConfig()->get("custom_base_damage"));

    $form->sendToPlayer($player);
}

private function toggleConfigSetting(string $configKey, string $displayName): void {
    $currentValue = $this->getConfig()->get($configKey);
    $newValue = !$currentValue; // Toggle the value
    $this->getConfig()->set($configKey, $newValue);
    $this->saveConfig();
	$this->loadConfigValues();
    $status = $newValue ? "enabled" : "disabled";
    $this->getServer()->broadcastMessage(TF::DARK_PURPLE . "$displayName is now $status.");
}
}
