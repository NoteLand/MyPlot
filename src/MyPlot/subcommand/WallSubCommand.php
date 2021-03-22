<?php


namespace MyPlot\subcommand;


use MyPlot\forms\MyPlotForm;
use MyPlot\forms\subforms\WallForm;
use MyPlot\MyPlot;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WallSubCommand extends SubCommand
{

    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.wall");
    }

    /**
     * @param Player $sender
     * @param string[] $args
     *
     * @return bool
     */
    public function execute(CommandSender $sender, array $args): bool
    {
        $plot = $this->getPlugin()->getPlotByPosition($sender);
        if($plot === null) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
            return true;
        }
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.wall")) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
            return true;
        }
        if (count(MyPlot::$walls) < 1) {
            $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("wall.empty"));
            return true;
        }
        $form = new WallForm();
        $form->setPlot($plot);
        $sender->sendForm($form);
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        if($player !== null and MyPlot::getInstance()->isLevelLoaded($player->getLevelNonNull()->getFolderName()) and (count(MyPlot::$walls) > 0))
            return new WallForm();
        return null;
    }
}