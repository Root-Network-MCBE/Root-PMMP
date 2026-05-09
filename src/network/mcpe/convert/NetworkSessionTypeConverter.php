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

namespace pocketmine\network\mcpe\convert;

use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\GuiDataPickItemPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function array_merge;
use function implode;
use function sprintf;

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

			$lore[] = sprintf(self::ENCHANT_FORMAT, $name, self::ENCHANT_LEVEL[$instance->getLevel()] ?? (string) $instance->getLevel());
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

			$lore[] = sprintf(self::ENCHANT_FORMAT, $name, self::ENCHANT_LEVEL[$instance->getLevel()] ?? (string) $instance->getLevel());
			$cloned->removeEnchantment($enchant);
		}
		if (!empty($lore)) {
			$cloned->setLore(array_merge($lore, $itemStack->getLore()));
		}

		return parent::coreItemStackToNet($cloned);
	}
}
