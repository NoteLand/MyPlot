<?php

namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\utils\FlagsFactory;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class FlagsSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.flags");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		if(count($args) === 0) {
			foreach (FlagsFactory::getInstance()->getAll() as $flag_name => $type) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString(match ($type) {
						FlagsFactory::TYPE_STRING_STORAGE => "flags.usage.string",
						FlagsFactory::TYPE_POSITION_STORAGE => "flags.usage.position",
						default => "flags.usage.boolean"
					}, [$flag_name]));
			}
			return true;
		}
		$plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.flags")) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		$flag_name = $args[0];
		if (($type = FlagsFactory::getInstance()->getFlagType($flag_name)) == -1) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.not_exists", [$flag_name]));
			return true;
		}
		if (count($args) < 2) {
			switch ($type) {
				case FlagsFactory::TYPE_BOOLEAN:
					$value = $plot->getFlag($flag_name);
					if ($plot->setFlag($flag_name, !$value)) {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.success", [$flag_name]));
					} else $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
					break;
				case FlagsFactory::TYPE_STRING_STORAGE:
					if (($value = $plot->getFlag($flag_name, "")) == "") {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.info.not_added", [$flag_name]));
						return true;
					}
					$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.info.value", [$flag_name, $value]));
					break;
				case FlagsFactory::TYPE_POSITION_STORAGE:
					$position = $sender->getPosition();
					$pos = $position->getFloorX() . ";" . $position->getFloorY() . ";" . $position->getFloorZ();
					if ($plot->setFlag($flag_name, $pos)) {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.success", [$flag_name]));
					} else $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
					break;
				default:
					$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
			}
		} else {
			$value = $args[1];
			if ($value == "") {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("subcommand.usage", [$this->getUsage()]));
				return true;
			}
			if ($value == "remove") {
				if ($plot->removeFlag($flag_name)) {
					$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.removed", [$flag_name]));
				} else $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
				return true;
			}
			switch ($type) {
				case FlagsFactory::TYPE_BOOLEAN:
					$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString(($plot->getFlag($flag_name) ? "flags.info.true" : "flags.info.false"), [$flag_name]));
					break;
				case FlagsFactory::TYPE_STRING_STORAGE:
					if ($plot->setFlag($flag_name, $value)) {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.success", [$flag_name]));
					} else $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
					break;
				case FlagsFactory::TYPE_POSITION_STORAGE:
					$position = explode(";", $plot->getFlag($flag_name));
					if (count($position) === 3 and is_numeric($position[0]) and is_numeric($position[1]) and is_numeric($position[2])) {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("flags.info.position", [$flag_name, $position[0], $position[1], $position[2]]));
					} else {
						$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
					}
					break;
			}
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}