<?php

declare(strict_types=1);

namespace pocketmine\entity\behaviour;

use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class ArmorStandBehaviourRegistry{

	/** @var ArmorStandBehaviour[] */
	private array $behaviours = [];

	/** @var ArmorStandBehaviour[] */
	private array $armorBehavioursBySlot = [];

	private ArmorStandBehaviour $fallback;

	public function __construct(){
		$this->registerFallback(new HeldItemArmorStandBehaviour());

		$this->register(VanillaBlocks::MOB_HEAD()->asItem(), new ArmorPieceArmorStandBehaviour(ArmorInventory::SLOT_HEAD));
		$this->register(VanillaBlocks::CARVED_PUMPKIN()->asItem(), new ArmorPieceArmorStandBehaviour(ArmorInventory::SLOT_HEAD));
		$this->register(VanillaItems::AIR(), new NullItemArmorStandBehaviour());
	}

	public function register(Item $item, ArmorStandBehaviour $behaviour) : void{
		$this->behaviours[$item->getTypeId()] = $behaviour;
	}

	public function registerFallback(ArmorStandBehaviour $behaviour) : void{
		$this->fallback = $behaviour;
	}

	public function get(Item $item) : ArmorStandBehaviour{
		if(isset($this->behaviours[$item->getTypeId()])){
			return $this->behaviours[$item->getTypeId()];
		}

		if($item instanceof Armor){
			return $this->armorBehavioursBySlot[$item->getArmorSlot()] ??= new ArmorPieceArmorStandBehaviour($item->getArmorSlot());
		}

		return $this->fallback;
	}
}