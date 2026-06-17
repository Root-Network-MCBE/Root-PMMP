<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\utils\VaultState;
use pocketmine\block\Vault as BlockVault;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\world\sound\VaultEjectSound;
use pocketmine\world\World;
use function array_map;
use function array_rand;
use function array_shift;
use function array_values;
use function count;
use function in_array;
use function mt_rand;

// PocketMine n'a pas forcément le son VAULT_EJECT, XpPickup s'en rapproche visuellement

class Vault extends Spawnable {
	public const TAG_CONFIG = "config";
	public const TAG_SERVER_DATA = "server_data";
	public const DATA = "data";

	public const TAG_LOOT_TABLE = "loot_table";
	public const TAG_OVERRIDE_LOOT_TABLE_TO_DISPLAY = "override_loot_table_to_display";
	public const TAG_ACTIVATION_RANGE = "activation_range";
	public const TAG_DEACTIVATION_RANGE = "deactivation_range";
	public const TAG_KEY_ITEM = "key_item";

	public const TAG_REWARDED_PLAYERS = "rewarded_players";
	public const TAG_STATE_UPDATING_RESUMES_AT = "state_updating_resumes_at";
	public const TAG_ITEMS_TO_EJECT = "items_to_eject";
	public const TAG_TOTAL_EJECTIONS_NEEDED = "total_ejections_needed";

	public const TAG_DISPLAY_ITEM = "display_item";
	public const TAG_CONNECTED_PLAYERS = "connected_players";
	public const TAG_CONNECTED_PARTICLES_RANGE = "connected_particles_range";

	public const DEFAULT_LOOT_TABLE = "minecraft:chests/trial_chambers/reward";
	public const DEFAULT_ACTIVATION_RANGE = 4.0;
	public const DEFAULT_DEACTIVATION_RANGE = 4.5;
	public const DEFAULT_CONNECTED_PARTICLES_RANGE = 4.5;

	private string $lootTable = self::DEFAULT_LOOT_TABLE;
	private string $overrideLootTableToDisplay = "";
	private float $activationRange = self::DEFAULT_ACTIVATION_RANGE;
	private float $deactivationRange = self::DEFAULT_DEACTIVATION_RANGE;
	private Item $keyItem;

	/** @var string[] */
	private array $rewardedPlayers = [];
	private int $stateUpdatingResumesAt = 0;
	/** @var Item[] */
	private array $itemsToEject = [];
	private int $totalEjectionsNeeded = 0;

	private Item $displayItem;
	/** @var string[] */
	private array $connectedPlayers = [];
	private float $connectedParticlesRange = self::DEFAULT_CONNECTED_PARTICLES_RANGE;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->keyItem = VanillaItems::TRIAL_KEY();
		$this->displayItem = VanillaItems::AIR();
	}

	public function readSaveData(CompoundTag $nbt) : void{
		if(($config = $nbt->getCompoundTag(self::TAG_CONFIG)) !== null){
			$this->lootTable = $config->getString(self::TAG_LOOT_TABLE, self::DEFAULT_LOOT_TABLE);
			$this->overrideLootTableToDisplay = $config->getString(self::TAG_OVERRIDE_LOOT_TABLE_TO_DISPLAY, "");
			$this->activationRange = $config->getFloat(self::TAG_ACTIVATION_RANGE, self::DEFAULT_ACTIVATION_RANGE);
			$this->deactivationRange = $config->getFloat(self::TAG_DEACTIVATION_RANGE, self::DEFAULT_DEACTIVATION_RANGE);
			if(($keyNbt = $config->getCompoundTag(self::TAG_KEY_ITEM)) !== null){
				$this->keyItem = Item::nbtDeserialize($keyNbt);
			}
		}

		if(($serverData = $nbt->getCompoundTag(self::TAG_SERVER_DATA)) !== null){
			$this->rewardedPlayers = [];
			if(($rewardedList = $serverData->getListTag(self::TAG_REWARDED_PLAYERS)) !== null){
				/** @var StringTag $tag */
				foreach($rewardedList->getValue() as $tag){
					$this->rewardedPlayers[] = $tag->getValue();
				}
			}
			$this->stateUpdatingResumesAt = $serverData->getInt(self::TAG_STATE_UPDATING_RESUMES_AT, 0);

			$this->itemsToEject = [];
			if(($ejectList = $serverData->getListTag(self::TAG_ITEMS_TO_EJECT)) !== null){
				/** @var CompoundTag $tag */
				foreach($ejectList->getValue() as $tag){
					$this->itemsToEject[] = Item::nbtDeserialize($tag);
				}
			}
			$this->totalEjectionsNeeded = $serverData->getInt(self::TAG_TOTAL_EJECTIONS_NEEDED, 0);
		}

		if(($sharedData = $nbt->getCompoundTag(self::DATA)) !== null){
			if(($displayNbt = $sharedData->getCompoundTag(self::TAG_DISPLAY_ITEM)) !== null){
				$this->displayItem = Item::nbtDeserialize($displayNbt);
			}
			$this->connectedPlayers = [];
			if(($connectedList = $sharedData->getListTag(self::TAG_CONNECTED_PLAYERS)) !== null){
				/** @var StringTag $tag */
				foreach($connectedList->getValue() as $tag){
					$this->connectedPlayers[] = $tag->getValue();
				}
			}
			$this->connectedParticlesRange = $sharedData->getFloat(self::TAG_CONNECTED_PARTICLES_RANGE, self::DEFAULT_CONNECTED_PARTICLES_RANGE);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setTag(self::TAG_CONFIG, $this->createConfigTag());
		$nbt->setTag(self::TAG_SERVER_DATA, $this->createServerDataTag());
		$nbt->setTag(self::DATA, $this->createSharedDataTag());
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setTag(self::TAG_CONFIG, $this->createConfigTag());
		$nbt->setTag(self::DATA, $this->createSharedDataTag());
	}

	public function onUpdate() : bool{
		if($this->closed) return false;

		$world = $this->position->getWorld();
		$block = $this->getBlock();
		$tick = $world->getServer()->getTick();

		if(!$block instanceof BlockVault){
			$this->close();
			return false;
		}

		if($tick % 5 === 0 && empty($this->itemsToEject)){
			$blockBB = $block->getCollisionBoxes()[0]->offset($this->position->x, $this->position->y, $this->position->z);

			$deactivationBox = $blockBB->expandedCopy($this->deactivationRange, $this->deactivationRange, $this->deactivationRange);
			$entitiesInDeactivation = $world->getCollidingEntities($deactivationBox);

			$changed = false;
			foreach($this->connectedPlayers as $index => $uuid){
				$found = false;
				foreach($entitiesInDeactivation as $entity){
					if($entity instanceof Player && $entity->getUniqueId()->toString() === $uuid){
						if(!$entity->isSpectator() && !in_array($uuid, $this->rewardedPlayers, true)){
							$found = true;
							break;
						}
					}
				}
				if(!$found){
					unset($this->connectedPlayers[$index]);
					$changed = true;
				}
			}
			$this->connectedPlayers = array_values($this->connectedPlayers);

			$activationBox = $blockBB->expandedCopy($this->activationRange, $this->activationRange, $this->activationRange);
			foreach($world->getCollidingEntities($activationBox) as $entity){
				if($entity instanceof Player){
					$uuid = $entity->getUniqueId()->toString();
					if(!in_array($uuid, $this->rewardedPlayers, true) && !in_array($uuid, $this->connectedPlayers, true) && !$entity->isSpectator()){
						$this->connectedPlayers[] = $uuid;
						$changed = true;
					}
				}
			}

			if($changed){
				$this->setDirty();
				if($block->getState() !== VaultState::UNLOCKING && $block->getState() !== VaultState::EJECTING){
					$block->setState(empty($this->connectedPlayers) ? VaultState::INACTIVE : VaultState::ACTIVE);
					$world->setBlock($this->position, $block);
				}
			}
		}

		if($tick % 20 === 0 && !empty($this->itemsToEject)){
			if($block->getState() === VaultState::UNLOCKING){
				$block->setState(VaultState::EJECTING);
				$world->setBlock($this->position, $block);
				return true;
			}

			$item = array_shift($this->itemsToEject);

			$world->dropItem($this->position->add(0.5, 0.8, 0.5), $item, new Vector3(0, 0.2, 0));
			$world->addSound($this->position, new VaultEjectSound());

			if(!empty($this->itemsToEject)){
				$this->setDisplayItem($this->itemsToEject[0]);
			} else {
				$this->setDisplayItem(VanillaItems::AIR());
				$block->setState(empty($this->connectedPlayers) ? VaultState::INACTIVE : VaultState::ACTIVE);
				$world->setBlock($this->position, $block);
			}
		}

		return true;
	}

	public function tryOpen(Player $player, Item $itemUsed) : bool{
		$uuid = $player->getUniqueId()->toString();
		$block = $this->getBlock();

		if(!$block instanceof BlockVault) return false;

		if(in_array($uuid, $this->rewardedPlayers, true)){
			return false;
		}

		$isOminous = $block->isOminous();
		if($itemUsed->getTypeId() !== $this->keyItem->getTypeId()){
			return false;
		}

		$itemUsed->pop();
		$player->getInventory()->setItemInHand($itemUsed);

		$this->rewardedPlayers[] = $uuid;

		$block->setState(VaultState::UNLOCKING);
		$this->position->getWorld()->setBlock($this->position, $block);

		$this->simulateLootRoll($isOminous);
		return true;
	}

	private function simulateLootRoll(bool $ominous) : void{
		$this->itemsToEject = [];
		$count = mt_rand(2, 4);

		$pool = $ominous ? [
			VanillaItems::DIAMOND(),
			VanillaItems::EMERALD(),
		] : [
			VanillaItems::IRON_INGOT(),
			VanillaItems::EMERALD(),
			VanillaItems::ARROW(),
			VanillaItems::DIAMOND()
		];

		for($i = 0; $i < $count; $i++){
			$this->itemsToEject[] = clone $pool[array_rand($pool)];
		}

		$this->totalEjectionsNeeded = count($this->itemsToEject);
		$this->setDisplayItem($this->itemsToEject[0]);
	}

	public function setDisplayItem(Item $item) : void{
		$this->displayItem = clone $item;
		$this->setDirty();
	}

	private function createConfigTag() : CompoundTag{
		return CompoundTag::create()
			->setString(self::TAG_LOOT_TABLE, $this->lootTable)
			->setFloat(self::TAG_ACTIVATION_RANGE, $this->activationRange)
			->setFloat(self::TAG_DEACTIVATION_RANGE, $this->deactivationRange)
			->setTag(self::TAG_KEY_ITEM, $this->keyItem->nbtSerialize())
			->setString(self::TAG_OVERRIDE_LOOT_TABLE_TO_DISPLAY, $this->overrideLootTableToDisplay);
	}

	private function createServerDataTag() : CompoundTag{
		return CompoundTag::create()
			->setTag(self::TAG_REWARDED_PLAYERS, new ListTag(array_map(fn($uid) => new StringTag($uid), $this->rewardedPlayers)))
			->setInt(self::TAG_STATE_UPDATING_RESUMES_AT, $this->stateUpdatingResumesAt)
			->setTag(self::TAG_ITEMS_TO_EJECT, new ListTag(array_map(fn(Item $i) => $i->nbtSerialize(), $this->itemsToEject)))
			->setInt(self::TAG_TOTAL_EJECTIONS_NEEDED, $this->totalEjectionsNeeded);
	}

	private function createSharedDataTag() : CompoundTag{
		$nbt = CompoundTag::create()
			->setTag(self::TAG_CONNECTED_PLAYERS, new ListTag(array_map(fn($uid) => new StringTag($uid), $this->connectedPlayers)))
			->setFloat(self::TAG_CONNECTED_PARTICLES_RANGE, $this->connectedParticlesRange);

		if (!$this->displayItem->isNull()) {
			$nbt->setTag(self::TAG_DISPLAY_ITEM, $this->displayItem->nbtSerialize());
		}
		return $nbt;
	}
}
