<?php

namespace MyPlot\utils;

use pocketmine\utils\SingletonTrait;

class FlagsFactory {
	use SingletonTrait;

	/** @var int[] $flags */
	private array $flags = [];

	public const TYPE_BOOLEAN = 0;
	public const TYPE_STRING_STORAGE = 1;
	public const TYPE_POSITION_STORAGE = 2;

	public function __construct() {
		$this->register(Flags::BREAK, self::TYPE_BOOLEAN);
		$this->register(Flags::BURNING, self::TYPE_BOOLEAN);
		$this->register(Flags::CHAT, self::TYPE_BOOLEAN);
		$this->register(Flags::DESCRIPTION, self::TYPE_STRING_STORAGE);
		$this->register(Flags::EXPLOSION, self::TYPE_BOOLEAN);
		$this->register(Flags::FAREWELL, self::TYPE_STRING_STORAGE);
		$this->register(Flags::FLOWING, self::TYPE_BOOLEAN);
		$this->register(Flags::FLY, self::TYPE_BOOLEAN);
		$this->register(Flags::GROWING, self::TYPE_BOOLEAN);
		$this->register(Flags::ITEM_DROP, self::TYPE_BOOLEAN);
		$this->register(Flags::ITEM_PICKUP, self::TYPE_BOOLEAN);
		$this->register(Flags::PLACE, self::TYPE_BOOLEAN);
		$this->register(Flags::INTERACT, self::TYPE_BOOLEAN);
		$this->register(Flags::PVE, self::TYPE_BOOLEAN);
		$this->register(Flags::PVP, self::TYPE_BOOLEAN);
		$this->register(Flags::SPAWN, self::TYPE_POSITION_STORAGE);
		$this->register(Flags::WELCOME, self::TYPE_STRING_STORAGE);
	}

	private function register(string $flag_name, int $type) : void {
		$this->flags[$flag_name] = $type;
	}

	public function getFlagType(string $flag_name) : int {
		return $this->flags[$flag_name] ?? -1;
	}

	/**
	 * @internal
	 *
	 * @return int[]
	 */
	public function getAll() : array {
		return $this->flags;
	}
}