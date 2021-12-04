<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class PvpSubCommand extends SubCommand {

	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.pvp");
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
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED.$this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.pvp")) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED.$this->translateString("notowner"));
			return true;
		}
		$levelSettings = $this->getOwningPlugin()->getLevelSettings($sender->getPosition()->getWorld()->getFolderName());
		if($levelSettings->restrictPVP) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED.$this->translateString("pvp.world"));
			return true;
		}
		if($this->getOwningPlugin()->setPlotPvp($plot, !$plot->pvp)) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("pvp.success", [!$plot->pvp ? "enabled" : "disabled"]));
		}else {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED.$this->translateString("error"));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}