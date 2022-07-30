<?php

namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\UnmergePlotTast;
use MyPlot\utils\DirectionFactory;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class UnmergeSubCommand extends SubCommand
{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender): bool
	{
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.unmerge");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args): bool
	{
		if (($plot = $this->getPlugin()->getPlotByPosition($sender->getPosition())) === null) {
			if (($plot = $this->getPlugin()->getPlotBorderingPosition($sender->getPosition())) === null) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notinplot"));
				return true;
			}
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.unmerge")) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
			return true;
		}
		if (($facing = $sender->getHorizontalFacing()) === null) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("merge.wrongFacing"));
			return true;
		}
		if (empty($args[0])) {
			$plotN = $plot->getSide($facing);
			if($plotN->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.merge")) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
				return true;
			}
			if (($direction = DirectionFactory::getInstance()->getDirection($facing)) == null) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("merge.wrongFacing"));
				return true;
			}
			if ($plot->isMerged($direction->getDirectionName() . "merge") and $plotN->isMerged($direction->getOppositeDirectionName() . "merge")) {
				$this->getPlugin()->getScheduler()->scheduleTask(new UnmergePlotTast($this->getPlugin(), $plot, $direction->getFacing()));
				$plot->removeMerge($direction->getDirectionName() . "merge");
				$plotN->removeMerge($direction->getOppositeDirectionName() . "merge");
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("unmerge.success"));
			} else {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("unmerge.notMerged"));
			}
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}