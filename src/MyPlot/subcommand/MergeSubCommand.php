<?php

namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\DirectionFactory;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class MergeSubCommand extends SubCommand
{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender): bool
	{
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.merge");
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
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.merge")) {
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
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("merge.alreadyMerged"));
			} else {
				$this->getPlugin()->mergePlot($plot, $plotN, $direction->getDirectionName());
				$plot->addMerge($direction->getDirectionName() . "merge");
				$plotN->addMerge($direction->getOppositeDirectionName() . "merge");
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("merge.success"));
			}
		} else {
			if ($args[0] === "auto") {
				$this->autoMerge($plot);
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("merge.success"));
			}
		}
		return true;
	}

	private function autoMerge(Plot $startPlot, int $tests = 1) : void {
		if ($tests < 8) {
			foreach (DirectionFactory::getInstance()->getAll() as $direction) {
				$plot = $startPlot->getSide($direction->getFacing());
				if ($startPlot->owner === $plot->owner) {
					if (!$startPlot->isMerged($direction->getDirectionName() . "merge") and !$plot->isMerged($direction->getOppositeDirectionName() . "merge")) {
						MyPlot::getInstance()->mergePlot($startPlot, $plot, $direction->getDirectionName());
						$startPlot->addMerge($direction->getDirectionName() . "merge");
						$plot->addMerge($direction->getOppositeDirectionName() . "merge");
						$this->autoMerge($plot, ($tests + 1));
					}
				}
			}
		}
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}