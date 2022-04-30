<?php

declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\MyPlot;
use pocketmine\command\CommandSender;
use MyPlot\forms\MyPlotForm;
use pocketmine\player\Player;

class SpawnSubCommand extends SubCommand
{
    /**
     * @param CommandSender $sender
     *
     * @return bool
     */
    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.spawn");
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
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.spawn")) {
            $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
            return true;
        }
        if (isset($args[0])) {
            if ($args[0] === "remove") {
                $plot->removeFlag("spawn");
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("spawn.reset"));
                return true;
            }
        }
        $position = $sender->getPosition();
        $spawn = $position->getFloorX() . ";" . $position->getFloorY() . ";" . $position->getFloorZ();
        $plot->setFlag("spawn", $spawn);
        $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("spawn.success"));
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        return null;
    }
}