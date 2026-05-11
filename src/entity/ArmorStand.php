<?php

/**
 * .--.  .--.  .--. .---.
 * |   ):    ::    :  |
 * |--' |    ||    |  |
 * |  \ :    ;:    ;  |
 * '   ` `--'  `--'   '
 *       by Valres.
 *
 * FRA:
 * Ce code source est la propriété exclusive de Valres.
 * Toute utilisation, reproduction, modification ou distribution de ce code
 * sans autorisation écrite explicite est strictement interdite.
 *
 * ENG:
 * This source code is the exclusive property of Valres.
 * Any use, reproduction, modification, or distribution of this code
 * without explicit written authorization is strictly prohibited.
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\behaviour\ArmorStandBehaviourRegistry;
use pocketmine\entity\behaviour\ticker\ArmorStandEntityTicker;
use pocketmine\entity\behaviour\ticker\WobbleArmorStandEntityTicker;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\utils\ArmorStandPose;
use pocketmine\entity\utils\ArmorStandPoseRegistry;
use pocketmine\event\entity\ArmorStandMoveEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\PlayerChangeArmorStandPoseEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

class ArmorStand extends Living {
	private const TAG_ARMOR_INVENTORY = "ArmorInventory";
	private const TAG_HELD_ITEM = "HeldItem";
	private const TAG_POSE = "Pose";

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMOR_STAND;
	}

	protected int $maxDeadTicks = 0;

	private ArmorStandPose $pose;
	protected Item $itemInHand;
	protected bool $canBeMovedByCurrents = true;

	/** @var ArmorStandEntityTicker[] */
	protected array $tickers = [];

	private static ?ArmorStandBehaviourRegistry $behaviourRegistry = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.975, 0.5);
	}

	public function getName() : string{
		return "Armor Stand";
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->pose->getNetworkId());
	}

	private static function getBehaviourRegistry() : ArmorStandBehaviourRegistry{
		return self::$behaviourRegistry ??= new ArmorStandBehaviourRegistry();
	}

	private function getEquipmentClickOffset(Vector3 $clickPos) : Vector3{
		if($this->boundingBox->expandedCopy(0.25, 0.25, 0.25)->isVectorInside($clickPos)){
			return $clickPos->subtractVector($this->getPosition());
		}

		return $clickPos;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(!$player->canInteract($this->getLocation(), 10)){
			return false;
		}

		if($player->isSneaking()){
			$oldPose = $this->getPose();
			$newPose = ArmorStandPoseRegistry::instance()->next($oldPose);

			$ev = new PlayerChangeArmorStandPoseEvent($this, $oldPose, $newPose, $player);
			$ev->call();

			if(!$ev->isCancelled()){
				$this->setPose($ev->getNewPose());
			}

			return true;
		}

		self::getBehaviourRegistry()
			->get($player->getInventory()->getItemInHand())
			->handleEquipment($player, $this, $this->getEquipmentClickOffset($clickPos));

		return true;
	}

	public function getDrops() : array{
		$drops = $this->getArmorInventory()->getContents();
		if(!$this->itemInHand->isNull()){
			$drops[] = $this->itemInHand;
		}
		$drops[] = VanillaItems::ARMOR_STAND();
		return $drops;
	}

	public function getItemInHand() : Item{
		return $this->itemInHand;
	}

	public function setItemInHand(Item $item_in_hand) : void{
		$this->itemInHand = $item_in_hand;
		$packet = MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->getItemInHand())), 0, 0, ContainerIds::INVENTORY);
		foreach($this->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
	}

	public function getPose() : ArmorStandPose{
		return $this->pose;
	}

	public function setPose(ArmorStandPose $pose) : void{
		$this->pose = $pose;
		$this->networkPropertiesDirty = true;
		$this->scheduleUpdate();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->getItemInHand())), 0, 0, ContainerIds::INVENTORY));
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$armorInventoryTag = $nbt->getListTag(self::TAG_ARMOR_INVENTORY);
		if($armorInventoryTag !== null){
			$armorInventory = $this->getArmorInventory();
			/** @var CompoundTag $tag */
			foreach($armorInventoryTag as $tag){
				$armorInventory->setItem($tag->getByte("Slot"), Item::nbtDeserialize($tag));
			}
		}

		$itemInHandTag = $nbt->getCompoundTag(self::TAG_HELD_ITEM);
		$this->itemInHand = $itemInHandTag !== null ? Item::nbtDeserialize($itemInHandTag) : VanillaItems::AIR();

		$this->setPose(($tag_pose = $nbt->getTag(self::TAG_POSE)) instanceof StringTag ?
			ArmorStandPoseRegistry::instance()->get($tag_pose->getValue()) :
			ArmorStandPoseRegistry::instance()->default());
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$armor_pieces = [];
		foreach($this->getArmorInventory()->getContents() as $slot => $item){
			$armor_pieces[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ARMOR_INVENTORY, new ListTag($armor_pieces, NBT::TAG_Compound));

		if(!$this->itemInHand->isNull()){
			$nbt->setTag(self::TAG_HELD_ITEM, $this->itemInHand->nbtSerialize());
		}

		$nbt->setString(self::TAG_POSE, ArmorStandPoseRegistry::instance()->getIdentifier($this->pose));
		return $nbt;
	}

	public function applyDamageModifiers(EntityDamageEvent $source) : void{
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source instanceof EntityDamageByChildEntityEvent && $source->getChild() instanceof Arrow){
			$this->kill();
		}
	}

	public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4) : void{
	}

	public function actuallyKnockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4) : void{
		parent::knockBack($x, $z, $force, $verticalLimit);
	}

	protected function doHitAnimation() : void{
		if(
			$this->lastDamageCause instanceof EntityDamageByEntityEvent &&
			$this->lastDamageCause->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK &&
			$this->lastDamageCause->getDamager() instanceof Player
		){
			$this->addArmorStandEntityTicker("ticker:wobble", new WobbleArmorStandEntityTicker($this));
		}
	}

	protected function startDeathAnimation() : void{
	}

	public function addArmorStandEntityTicker(string $identifier, ArmorStandEntityTicker $ticker) : void{
		$this->tickers[$identifier] = $ticker;
		$this->scheduleUpdate();
	}

	public function removeArmorStandEntityTicker(string $identifier) : void{
		unset($this->tickers[$identifier]);
	}

	public function canBeMovedByCurrents() : bool{
		return $this->canBeMovedByCurrents;
	}

	public function setCanBeMovedByCurrents(bool $canBeMovedByCurrents) : void{
		$this->canBeMovedByCurrents = $canBeMovedByCurrents;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$result = parent::entityBaseTick($tickDiff);

		foreach($this->tickers as $identifier => $ticker){
			if(!$ticker->tick($this)){
				$this->removeArmorStandEntityTicker($identifier);
			}
		}

		return $result || count($this->tickers) > 0;
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		$from = $this->location->asLocation();
		parent::move($dx, $dy, $dz);
		$to = $this->location->asLocation();
		(new ArmorStandMoveEvent($this, $from, $to))->call();
	}

}