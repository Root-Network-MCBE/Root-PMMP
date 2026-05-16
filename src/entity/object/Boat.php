<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |/| | | '_ \ / _ \_____| |/| | |_) |
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

namespace pocketmine\entity\object;

use pocketmine\block\Water;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\BoatType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use function abs;
use function array_filter;
use function array_map;
use function array_values;
use function cos;
use function deg2rad;
use function floor;
use function max;
use function mb_strtoupper;
use function min;
use function sin;
use function spl_object_id;
use function strtolower;

class Boat extends Entity implements InventoryHolder{
	protected const BASE_OFFSET = 0.375;

	private const SINKING_DEPTH = 0.07;
	private const SINKING_SPEED = 0.0005;
	private const SINKING_MAX_SPEED = 0.005;

	public const TAG_TYPE = "Variant"; //TAG_Int
	public const TAG_WITH_CHEST = "Chest"; //TAG_Byte
	public const TAG_ITEMS = "Items"; //TAG_List<TAG_Compound>

	private BoatType $boatType;
	private bool $withChest;
	private ?BoatInventory $inventory = null;
	private ?Player $rider = null;
	private ?Player $passenger = null;

	protected int $rollingAmplitude = 0;
	protected bool $rollingDirection = false;

	public function __construct(Location $location, BoatType $boatType = BoatType::OAK, bool $withChest = false, ?CompoundTag $nbt = null){
		$this->boatType = $boatType;
		$this->withChest = $withChest;
		if($withChest){
			$this->inventory = new BoatInventory($this);
		}
		parent::__construct($location, $nbt);
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::BOAT;
	}

	protected function sendSpawnPacket(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			$this->withChest ? EntityIds::CHEST_BOAT : EntityIds::BOAT,
			$this->getOffsetPosition($this->location->asVector3()),
			$this->getMotion(),
			$this->location->pitch,
			$this->location->yaw,
			$this->location->yaw,
			$this->location->yaw,
			array_map(function(Attribute $attr) : NetworkAttribute{
				return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []);
			}, $this->attributeMap->getAll()),
			$this->getAllNetworkData(),
			new PropertySyncData([], []),
			array_values(array_filter([
				$this->rider !== null ? new EntityLink(
					$this->getId(),
					$this->rider->getId(),
					EntityLink::TYPE_RIDER,
					true,
					true,
					0.0
				) : null,
				$this->passenger !== null ? new EntityLink(
					$this->getId(),
					$this->passenger->getId(),
					EntityLink::TYPE_PASSENGER,
					true,
					false,
					0.0
				) : null
			]))
		));
	}

	public function getBoatType() : BoatType{
		return $this->boatType;
	}

	public function hasChest() : bool{
		return $this->withChest;
	}

	public function getInventory() : Inventory{
		if($this->inventory === null){
			throw new \LogicException("This boat doesn't have an inventory");
		}
		return $this->inventory;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.455, 1.4);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.1;
	}

	protected function getInitialGravity() : float{
		return 0.04;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(4);
		$this->setHealth(4);

		$this->boatType = BoatType::fromNetworkVariant($nbt->getInt(self::TAG_TYPE, $this->boatType->getNetworkVariant())) ?? $this->boatType;
		$this->withChest = $nbt->getByte(self::TAG_WITH_CHEST, $this->withChest ? 1 : 0) !== 0;
		if($this->withChest && $this->inventory === null){
			$this->inventory = new BoatInventory($this);
		}
		$this->loadInventory($nbt);

		parent::initEntity($nbt);
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->boatType->getNetworkVariant());
		$properties->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
		$properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, true);
		$properties->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, false);
		$properties->setGenericFlag(EntityMetadataFlags::CHESTED, $this->withChest);
		$properties->setGenericFlag(EntityMetadataFlags::STACKABLE, true);
		$properties->setInt(EntityMetadataProperties::HEALTH, (int) ($this->getMaxHealth() - $this->getHealth()));
		$properties->setInt(EntityMetadataProperties::HURT_TIME, $this->rollingAmplitude);
		$properties->setInt(EntityMetadataProperties::HURT_DIRECTION, $this->rollingDirection ? 1 : -1);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, 0.0);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, 0.0);
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
		$properties->setByte(EntityMetadataProperties::IS_BUOYANT, 1);
		$properties->setString(EntityMetadataProperties::BUOYANCY_DATA, "{\"apply_gravity\":true,\"base_buoyancy\":1.0,\"big_wave_probability\":0.03,\"big_wave_speed\":10.0,\"drag_down_on_buoyancy_removed\":0.0,\"liquid_blocks\":[\"minecraft:water\",\"minecraft:flowing_water\"],\"simulate_waves\":true}");
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_TYPE, $this->boatType->getNetworkVariant());
		$nbt->setByte(self::TAG_WITH_CHEST, $this->withChest ? 1 : 0);
		$this->saveInventory($nbt);
		return $nbt;
	}

	private function loadInventory(CompoundTag $nbt) : void{
		if($this->inventory === null){
			return;
		}
		try{
			$itemsTag = $nbt->getListTag(self::TAG_ITEMS, CompoundTag::class);
		}catch(UnexpectedTagTypeException){
			$itemsTag = null;
		}
		if($itemsTag === null){
			return;
		}

		$contents = [];
		foreach($itemsTag as $itemTag){
			$slot = $itemTag->getByte(SavedItemStackData::TAG_SLOT);
			$contents[$slot] = Item::safeNbtDeserialize($itemTag, "Boat inventory slot $slot");
		}
		$this->inventory->setContents($contents);
	}

	private function saveInventory(CompoundTag $nbt) : void{
		if($this->inventory === null){
			return;
		}
		$items = [];
		foreach($this->inventory->getContents() as $slot => $item){
			$items[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isSneaking() && $this->inventory !== null){
			$player->setCurrentWindow($this->inventory);
			return true;
		}
		if($this->rider === null){
			$this->dismountFromAnyBoat($player);
			$this->setRider($player);
			return true;
		}
		if($this->rider === $player || $this->passenger === $player){
			return true;
		}
		if($this->inventory !== null){
			$player->setCurrentWindow($this->inventory);
			return true;
		}
		if($this->passenger === null){
			$this->dismountFromAnyBoat($player);
			$this->setPassenger($player);
			return true;
		}
		return false;
	}

	public function setRider(?Player $player) : void{
		if($this->rider === $player){
			return;
		}
		$this->dismountRider();
		$this->rider = $player;
		if($player !== null){
			$this->applySeatProperties($player, new Vector3(0.2, 1.02, 0.0));
			$this->setMotion(Vector3::zero());
			$this->keepMovement = true;
			NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [SetActorLinkPacket::create(new EntityLink(
				$this->getId(),
				$player->getId(),
				EntityLink::TYPE_RIDER,
				true,
				true,
				0.0
			))]);
		}
	}

	public function getRider() : ?Player{
		return $this->rider;
	}

	public function dismountRider(bool $promotePassenger = true) : void{
		if($this->rider === null){
			return;
		}
		$rider = $this->rider;
		$this->resetSeatProperties($rider);
		NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [SetActorLinkPacket::create(new EntityLink(
			$this->getId(),
			$rider->getId(),
			EntityLink::TYPE_REMOVE,
			true,
			true,
			0.0
		))]);

		if($promotePassenger && $this->passenger !== null){
			$passenger = $this->passenger;
			$this->dismountPassenger();
			$this->rider = null;
			$this->setRider($passenger);
		}else{
			$this->rider = null;
			$this->keepMovement = false;
		}
	}

	public function setPassenger(?Player $player) : void{
		if($this->passenger === $player){
			return;
		}
		$this->dismountPassenger();
		$this->passenger = $player;
		if($player !== null){
			$this->applySeatProperties($player, new Vector3(-0.6, 1.02, 0.0));
			NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [SetActorLinkPacket::create(new EntityLink(
				$this->getId(),
				$player->getId(),
				EntityLink::TYPE_PASSENGER,
				true,
				false,
				0.0
			))]);
		}
	}

	public function getPassenger() : ?Player{
		return $this->passenger;
	}

	public function dismountPassenger() : void{
		if($this->passenger === null){
			return;
		}
		$passenger = $this->passenger;
		$this->passenger = null;
		$this->resetSeatProperties($passenger);
		NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [SetActorLinkPacket::create(new EntityLink(
			$this->getId(),
			$passenger->getId(),
			EntityLink::TYPE_REMOVE,
			true,
			false,
			0.0
		))]);
	}

	private function applySeatProperties(Player $player, Vector3 $seatPosition) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $seatPosition);
		$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 1);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, 0.0);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, 90.0);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, -90.0);
		$player->sendData(null);
	}

	private function resetSeatProperties(Player $player) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, Vector3::zero());
		$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
		$player->sendData(null);
	}

	private function dismountFromAnyBoat(Player $player) : void{
		foreach($player->getWorld()->getEntities() as $entity){
			if($entity instanceof self){
				if($entity->getRider() === $player){
					$entity->dismountRider();
				}elseif($entity->getPassenger() === $player){
					$entity->dismountPassenger();
				}
			}
		}
	}

	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool{
		if($this->rider !== $player){
			return false;
		}
		$vehicleInfo = $packet->getVehicleInfo();
		if($vehicleInfo !== null && $vehicleInfo->getPredictedVehicleActorUniqueId() !== $this->getId()){
			return false;
		}

		$yaw = $packet->getYaw();
		$this->location->yaw = $yaw;
		$this->location->pitch = 0.0;
		$speed = $this->getWaterLevel() !== INF ? 0.18 : 0.08;
		$forward = $packet->getMoveVecZ();
		$strafe = $packet->getMoveVecX();
		if(abs($forward) > 0.01 || abs($strafe) > 0.01){
			$rad = deg2rad($yaw);
			$dx = (-sin($rad) * $forward + cos($rad) * $strafe) * $speed;
			$dz = (cos($rad) * $forward + sin($rad) * $strafe) * $speed;
			$this->motion = new Vector3($dx, 0.0, $dz);
		}else{
			$this->motion = new Vector3($this->motion->x * 0.4, 0.0, $this->motion->z * 0.4);
		}
		$this->scheduleUpdate();
		return true;
	}

	protected function tryChangeMovement() : void{
		if($this->rider !== null){
			return;
		}

		$mY = $this->motion->y;
		$waterDiff = $this->getWaterLevel();

		if($waterDiff !== INF){
			if($waterDiff > self::SINKING_DEPTH){
				$mY = $waterDiff > 0.5 ? $mY - $this->gravity : ($mY - self::SINKING_SPEED < -self::SINKING_MAX_SPEED ? $mY : $mY - self::SINKING_SPEED);
			}elseif($waterDiff < -self::SINKING_DEPTH){
				$mY = min(0.05, $mY + 0.005);
			}elseif($waterDiff < 0){
				$mY = min(0.02, $mY + self::SINKING_SPEED);
			}else{
				$mY = $mY > self::SINKING_MAX_SPEED ? max($mY - 0.02, self::SINKING_MAX_SPEED) : $mY + self::SINKING_SPEED;
			}

			$this->checkObstruction($this->location->x, $this->location->y, $this->location->z);
			$friction = 1 - $this->drag;
			if($this->onGround){
				$friction *= $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y - 1), (int) floor($this->location->z))->getFrictionFactor();
			}
			$this->motion = new Vector3($this->motion->x * $friction, $mY, $this->motion->z * $friction);
			return;
		}
		parent::tryChangeMovement();
	}

	protected function getWaterLevel() : float{
		$maxY = $this->boundingBox->minY + self::BASE_OFFSET;

		$diffY = INF;
		foreach($this->getBlocksAroundWithEntityInsideActions() as $block){
			if($block instanceof Water){
				$level = ($block->getPosition()->getY() + 1) - ($block->getFluidHeightPercent() - 0.1111111);
				$diffY = min($maxY - $level, $diffY);
			}
		}

		return $diffY;
	}

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return $vector3->add(0.0, self::BASE_OFFSET, 0.0);
	}

	public function setNoClientPredictions(bool $value = true) : void{
		// Boats rely on client-side vehicle prediction while linked to a rider.
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->rollingAmplitude > 0){
			--$this->rollingAmplitude;
			$this->networkPropertiesDirty = true;
		}
		if($this->rider !== null){
			if($this->rider->isClosed() || $this->rider->getWorld() !== $this->getWorld()){
				$this->dismountRider();
			}
		}
		if($this->passenger !== null){
			if($this->passenger->isClosed() || $this->passenger->getWorld() !== $this->getWorld()){
				$this->dismountPassenger();
			}
		}
		return parent::entityBaseTick($tickDiff);
	}

	protected function onDeath() : void{
		parent::onDeath();
		$this->dismountRider(false);
		$this->dismountPassenger();

		$drops = true;
		if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
			$killer = $this->lastDamageCause->getDamager();
			if($killer instanceof Player && !$killer->hasFiniteResources()){
				$drops = false;
			}
		}

		if($drops){
			$this->getWorld()->dropItem($this->location, $this->getPickedItem() ?? VanillaItems::OAK_BOAT());
			if($this->inventory !== null){
				foreach($this->inventory->getContents() as $item){
					$this->getWorld()->dropItem($this->location, $item);
				}
				$this->inventory->clearAll();
			}
		}
		$this->getWorld()->addParticle($this->location->add(0.5, 0.2, 0.5), new BlockBreakParticle(VanillaBlocks::OAK_PLANKS()));
	}

	public function getPickedItem() : ?Item{
		$name = $this->boatType->getItemNameStem();
		if($this->withChest){
			$name = $this->boatType === BoatType::BAMBOO ? "bamboo_chest_raft" : strtolower($this->boatType->name) . "_chest_boat";
		}
		return VanillaItems::getAll()[mb_strtoupper($name)] ?? null;
	}

	public function flagForDespawn() : void{
		$this->dismountRider(false);
		$this->dismountPassenger();
		parent::flagForDespawn();
	}

	public function despawnFrom(Player $player, bool $send = true) : void{
		parent::despawnFrom($player, $send);
		if($this->rider !== null && spl_object_id($this->rider) === spl_object_id($player)){
			$this->dismountRider();
		}elseif($this->passenger !== null && spl_object_id($this->passenger) === spl_object_id($player)){
			$this->dismountPassenger();
		}
	}

	protected function performHurtAnimation() : void{
		$this->rollingAmplitude = 9;
		$this->rollingDirection = !$this->rollingDirection;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && !$damager->hasFiniteResources()){
				$source->setBaseDamage(1000.0);
			}
		}

		parent::attack($source);

		if(!$source->isCancelled() && $this->isAlive()){
			$this->performHurtAnimation();
		}
	}
}
