<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class ClearSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.clear");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		$plot = $this->getOwningPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.clear")) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		if(isset($args[0]) and $args[0] == $this->translateString("confirm")) {
			$economy = $this->getOwningPlugin()->getEconomyProvider();
			$price = $this->getOwningPlugin()->getLevelSettings($plot->levelName)->clearPrice;
			if($economy !== null and !$economy->reduceMoney($sender, $price)) {
				$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("clear.nomoney"));
				return true;
			}
			$maxBlocksPerTick = $this->getOwningPlugin()->getConfig()->get("ClearBlocksPerTick", 256);
			if(!is_int($maxBlocksPerTick))
				$maxBlocksPerTick = 256;
			if($this->getOwningPlugin()->clearPlot($plot, $maxBlocksPerTick)) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("clear.success"));
			}else{
				$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
			}
		}else{
			$plotId = TextFormat::GREEN . $plot . TextFormat::WHITE;
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("clear.confirm", [$plotId]));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}