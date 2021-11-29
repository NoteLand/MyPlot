<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\forms\subforms\DenyPlayerForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DenyPlayerSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.denyplayer");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		if(count($args) === 0) {
			return false;
		}
		$dplayer = $args[0];
		$plot = $this->getOwningPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.denyplayer")) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		if($dplayer === "*") {
			if($this->getOwningPlugin()->addPlotDenied($plot, $dplayer)) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("denyplayer.success1", [$dplayer]));
				foreach($this->getOwningPlugin()->getServer()->getOnlinePlayers() as $player) {
					if($this->getOwningPlugin()->getPlotBB($plot)->isVectorInside($player->getPosition()->asVector3()) and !($player->getName() === $plot->owner) and !$player->hasPermission("myplot.admin.denyplayer.bypass") and !$plot->isHelper($player->getName()))
						$this->getOwningPlugin()->teleportPlayerToPlot($player, $plot);
					else {
						$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("denyplayer.cannotdeny", [$player->getName()]));
						$player->sendMessage($this->translateString("denyplayer.attempteddeny", [$sender->getName()]));
					}
				}
			}else{
				$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
			}
			return true;
		}
		$dplayer = $this->getOwningPlugin()->getServer()->getPlayerByPrefix($dplayer);
		if(!$dplayer instanceof Player) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("denyplayer.notaplayer"));
			return true;
		}
		if($dplayer->hasPermission("myplot.admin.denyplayer.bypass") or $dplayer->getName() === $plot->owner) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("denyplayer.cannotdeny", [$dplayer->getName()]));
			$dplayer->sendMessage($this->translateString("denyplayer.attempteddeny", [$sender->getName()]));
			return true;
		}
		if($this->getOwningPlugin()->addPlotDenied($plot, $dplayer->getName())) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("denyplayer.success1", [$dplayer->getName()]));
			$dplayer->sendMessage($this->translateString("denyplayer.success2", [$plot->X, $plot->Z, $sender->getName()]));
			if($this->getOwningPlugin()->getPlotBB($plot)->isVectorInside($dplayer->getPosition()->asVector3()))
				$this->getOwningPlugin()->teleportPlayerToPlot($dplayer, $plot);
		}else{
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		if($player !== null and ($plot = $this->getOwningPlugin()->getPlotByPosition($player->getPosition())) instanceof Plot)
			return new DenyPlayerForm($plot);
		return null;
	}
}