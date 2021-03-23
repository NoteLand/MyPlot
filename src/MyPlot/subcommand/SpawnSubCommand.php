<?php

declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\MyPlot;
use MyPlot\utils\Plot;
use pocketmine\command\CommandSender;
use MyPlot\forms\MyPlotForm;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

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
        if (($plot = $this->getPlugin()->getPlotByPosition($sender)) === null) {
            if (($plot = $this->getPlugin()->getPlotBorderingPosition($sender)) === null) {
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notinplot"));
                return true;
            }
        }
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.spawn")) {
            $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
            return true;
        }
        $plot->spawn = $sender->getPosition();
        $this->getPlugin()->getProvider()->savePlot($plot);
        $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("spawn.success"));
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        return null;
    }
}