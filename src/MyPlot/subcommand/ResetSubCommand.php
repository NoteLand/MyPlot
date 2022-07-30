<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\UnmergePlotTast;
use MyPlot\utils\DirectionFactory;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class ResetSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.reset");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		$plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.reset")) {
			$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		if(isset($args[0]) and $args[0] == $this->translateString("confirm")) {
			$economy = $this->getPlugin()->getEconomyProvider();
			$price = $this->getPlugin()->getLevelSettings($plot->levelName)->resetPrice;
			if($economy !== null and !$economy->reduceMoney($sender, $price)) {
				$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("reset.nomoney"));
				return true;
			}
			/** @var int $maxBlocksPerTick */
			$maxBlocksPerTick = $this->getPlugin()->getConfig()->get("ClearBlocksPerTick", 256);
			if($this->getPlugin()->resetPlot($plot, $maxBlocksPerTick)) {
				$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("reset.success"));
				foreach ($plot->merged_plots as $string) {
					$facing = DirectionFactory::getInstance()->facingFromString($string);
					$this->getPlugin()->getScheduler()->scheduleTask(new UnmergePlotTast($this->getPlugin(), $plot, $facing));
					if (($direction = DirectionFactory::getInstance()->getDirection($facing)) !== null) {
						$newPlot = $plot->getSide($facing);
						$newPlot->removeMerge($direction->getOppositeDirectionName() . "merge");
					}
				}
			}else{
				$sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("error"));
			}
		}else{
			$plotId = TextFormat::GREEN . $plot . TextFormat::WHITE;
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("reset.confirm", [$plotId]));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return null;
	}
}