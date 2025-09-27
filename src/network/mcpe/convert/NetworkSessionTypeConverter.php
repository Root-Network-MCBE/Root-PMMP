<?php

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\GuiDataPickItemPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class NetworkSessionTypeConverter extends TypeConverter
{
	private NetworkSession $networkSession;
	private const ENCHANT_FORMAT = "§r§7%s %s";
	private const ENCHANT_LEVEL = [
		1 => "I",
		2 => "II",
		3 => "III",
		4 => "IV",
		5 => "V",
		6 => "VI",
		7 => "VII",
		8 => "VIII",
		9 => "IX",
		10 => "X",
	];

	public function init(NetworkSession $networkSession) : void{
		$this->networkSession = $networkSession;
	}

	/**
	 * @return NetworkSession
	 */
	public function getNetworkSession() : NetworkSession{
		return $this->networkSession;
	}

	public function coreItemStackToGuiDataPickItem(Item $itemStack) : ?GuiDataPickItemPacket{
		$lang = Server::getInstance()->getMinecraftLanguage($this->networkSession->getPlayerInfo()->getLocale());
		if ($lang === null) {
			throw new PacketHandlingException("Could not find language for locale " . $this->networkSession->getPlayerInfo()->getLocale());
		}

		$enchantments = $itemStack->getEnchantments();
		if (empty($enchantments)) {
			return null;
		}

		$lore = [];
		foreach ($itemStack->getEnchantments() as $k => $instance) {
			$enchant = $instance->getType();
			$name = $enchant->getName();
			if ($name instanceof Translatable) {
				$name = $lang->translate($name);
			}

			$lore[] = sprintf(self::ENCHANT_FORMAT, $name, self::ENCHANT_LEVEL[$instance->getLevel()] ?? (string)$instance->getLevel());
		}

		return GuiDataPickItemPacket::create(
			TextFormat::AQUA . $itemStack->getName(),
			implode("\n", $lore),
			0
		);
	}

	public function coreItemStackToNet(Item $itemStack) : ItemStack{
		$cloned = clone $itemStack;

		$lang = Server::getInstance()->getMinecraftLanguage($this->networkSession->getPlayerInfo()->getLocale());
		if ($lang === null) {
			throw new PacketHandlingException("Could not find language for locale " . $this->networkSession->getPlayerInfo()->getLocale());
		}

		$lore = [];
		foreach ($itemStack->getEnchantments() as $k => $instance) {
			$enchant = $instance->getType();
			$name = $enchant->getName();
			if($name instanceof Translatable) {
				$name = $lang->translate($name);
			}

			$lore[] = sprintf(self::ENCHANT_FORMAT, $name, self::ENCHANT_LEVEL[$instance->getLevel()] ?? (string)$instance->getLevel());
			$cloned->removeEnchantment($enchant);
		}
		if (!empty($lore)) {
			$cloned->setLore(array_merge($lore, $itemStack->getLore()));
		}

		return parent::coreItemStackToNet($cloned);
	}
}