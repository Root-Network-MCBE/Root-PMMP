<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\entity\projectile\FishingHook;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;

class FishingRod extends Durable{

	private const COOLDOWN_TICKS = 5;
	private const HOOK_VELOCITY = 0.7;
	private const MAX_DURABILITY = 384;

	private const BASE_MIN_WAIT_TICKS = 100;
	private const BASE_MAX_WAIT_TICKS = 600;
	private const LURE_REDUCTION_TICKS = 100;

	private const LOOT_CONFIG = [
		"thresholds" => [
			0 => ["fish" => 850, "treasure" => 900],
			1 => ["fish" => 847, "treasure" => 923],
			2 => ["fish" => 845, "treasure" => 940],
			3 => ["fish" => 845, "treasure" => 959],
		],
		"fish_distribution" => [
			0 => ["cod" => 600, "salmon" => 850, "tropical" => 870],
			1 => ["cod" => 507, "salmon" => 719, "tropical" => 736],
			2 => ["cod" => 507, "salmon" => 718, "tropical" => 735],
			3 => ["cod" => 507, "salmon" => 718, "tropical" => 735],
		],
	];

	public function getCooldownTicks() : int{
		return self::COOLDOWN_TICKS;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return self::MAX_DURABILITY;
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$hook = $player->getFishingHook();

		if($hook !== null && !$hook->isClosed() && !$hook->isFlaggedForDespawn()){
			$this->handleExistingHook($player, $hook);
			return ItemUseResult::SUCCESS();
		}

		$this->castNewHook($player);
		return ItemUseResult::SUCCESS();
	}

	public function onInteractAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		return $this->onClickAir($player, $directionVector, $returnedItems);
	}

	private function damageRodInHand(Player $player, int $amount) : void{
		$item = $player->getInventory()->getItemInHand();
		if($item instanceof self){
			$item->setDamage($item->getDamage() + $amount);
			$player->getInventory()->setItemInHand($item);
		}
	}

	private function handleExistingHook(Player $player, FishingHook $hook) : void{
		$damage = 0;
		if($hook->didCatchSomething()){
			$damage += 1;
		}
		if($hook->getTargetEntity() !== null){
			$damage += mt_rand(1, 2);
		}

		$hook->reelLine();

		if($player->getFishingHook() === $hook){
			$player->setFishingHook(null);
		}

		if($damage > 0){
			$this->damageRodInHand($player, $damage);
		}
	}

	private function castNewHook(Player $player) : void{
		$location = $player->getLocation();
		$location->y += $player->getEyeHeight();

		$hook = new FishingHook($location, $player);
		$hook->setMotion($player->getDirectionVector()->multiply(self::HOOK_VELOCITY));
		$hook->setWaitingTimer(1);

		$event = new ProjectileLaunchEvent($hook);
		$event->call();

		if($event->isCancelled()){
			$hook->flagForDespawn();
			return;
		}

		$hook->spawnToAll();
		$location->getWorld()->addSound($location, new ThrowSound(), [$player]);

		$player->setFishingHook($hook);
	}

	public function getRandomReward() : Item{
		$luckLevel = max(0, min(3, $this->getEnchantmentLevel(VanillaEnchantments::LUCK_OF_THE_SEA())));
		return $this->generateLootByLuck($luckLevel);
	}

	private function generateLootByLuck(int $luckLevel) : Item{
		$random = mt_rand(1, 1000);
		$thresholds = self::LOOT_CONFIG["thresholds"][$luckLevel] ?? self::LOOT_CONFIG["thresholds"][0];

		return match(true){
			$random <= $thresholds["fish"] => $this->generateFish($luckLevel),
			$random <= $thresholds["treasure"] => $this->generateTreasure(),
			default => $this->generateWaste(),
		};
	}

	private function generateFish(int $luckLevel) : Item{
		$fishRandom = mt_rand(1, 1000);
		$distribution = self::LOOT_CONFIG["fish_distribution"][$luckLevel] ?? self::LOOT_CONFIG["fish_distribution"][0];

		return match(true){
			$fishRandom <= $distribution["cod"] => VanillaItems::RAW_COD(),
			$fishRandom <= $distribution["salmon"] => VanillaItems::RAW_SALMON(),
			$fishRandom <= $distribution["tropical"] => VanillaItems::TROPICAL_FISH(),
			default => VanillaItems::PUFFERFISH(),
		};
	}

	private function generateTreasure() : Item{
		$treasureItems = [
			VanillaItems::BOW(),
			VanillaItems::ENCHANTED_BOOK(),
			VanillaItems::FISHING_ROD(),
			VanillaItems::NAME_TAG(),
			VanillaItems::NAUTILUS_SHELL(),
			VanillaItems::SADDLE(),
		];

		$item = $treasureItems[array_rand($treasureItems)];

		if($item instanceof Bow){
			$this->applyRandomEnchantments($item, [
				VanillaEnchantments::POWER(),
				VanillaEnchantments::PUNCH(),
				VanillaEnchantments::FLAME(),
				VanillaEnchantments::INFINITY(),
				VanillaEnchantments::UNBREAKING(),
				VanillaEnchantments::MENDING(),
			]);
		}elseif($item instanceof self){
			$this->applyRandomEnchantments($item, [
				VanillaEnchantments::LUCK_OF_THE_SEA(),
				VanillaEnchantments::LURE(),
				VanillaEnchantments::UNBREAKING(),
				VanillaEnchantments::MENDING(),
			]);
		}

		return $item;
	}

	private function generateWaste() : Item{
		$wasteTable = [
			170 => fn() => VanillaItems::LEATHER(),
			270 => fn() => VanillaItems::BOWL(),
			290 => fn() => VanillaItems::FISHING_ROD()->setDamage(374 + mt_rand(0, 10)),
			390 => fn() => VanillaItems::LEATHER(),
			490 => fn() => VanillaItems::LEATHER_BOOTS()->setDamage(mt_rand(1, 64)),
			590 => fn() => VanillaItems::ROTTEN_FLESH(),
			640 => fn() => VanillaItems::STICK(),
			690 => fn() => VanillaItems::STRING(),
			790 => fn() => VanillaItems::POTION(),
			890 => fn() => VanillaItems::BONE(),
			900 => fn() => VanillaItems::INK_SAC()->setCount(10),
			1000 => fn() => VanillaItems::BONE(),
		];

		$random = mt_rand(1, 1000);

		foreach($wasteTable as $threshold => $generator){
			if($random <= $threshold){
				return $generator();
			}
		}
		return VanillaItems::BOWL();
	}

	/**
	 * @param Enchantment[] $pool
	 */
	private function applyRandomEnchantments(Item $item, array $pool) : void{
		if($pool === []){
			return;
		}

		$num = mt_rand(1, min(3, count($pool)));
		$idx = (array) array_rand($pool, $num);

		foreach($idx as $i){
			$ench = $pool[$i];
			$level = mt_rand(1, $ench->getMaxLevel());
			$item->addEnchantment(new EnchantmentInstance($ench, $level));
		}
	}

	public function calculateFishingWaitTime() : int{
		$lureLevel = $this->getEnchantmentLevel(VanillaEnchantments::LURE());

		$min = self::BASE_MIN_WAIT_TICKS - ($lureLevel * self::LURE_REDUCTION_TICKS);
		$max = self::BASE_MAX_WAIT_TICKS - ($lureLevel * self::LURE_REDUCTION_TICKS);

		if($min < 1 || $max < 1){
			return 1;
		}
		if($min > $max){
			[$min, $max] = [$max, $min];
		}
		return mt_rand($min, $max);
	}
}
