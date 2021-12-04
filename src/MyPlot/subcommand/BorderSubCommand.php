<?php


namespace MyPlot\subcommand;


use MyPlot\forms\MyPlotForm;
use MyPlot\forms\subforms\BorderForm;
use MyPlot\MyPlot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BorderSubCommand extends SubCommand
{

    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.border");
    }

    /**
     * @param Player $sender
     * @param string[] $args
     *
     * @return bool
     */
    public function execute(CommandSender $sender, array $args): bool
    {
        $plot = $this->getOwningPlugin()->getPlotByPosition($sender->getPosition());
        if($plot === null) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
            return true;
        }
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.border")) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
            return true;
        }
        if (count(MyPlot::$borders) < 1) {
            $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("border.empty"));
            return true;
        }
        $form = new BorderForm();
        $form->setPlot($plot);
        $sender->sendForm($form);
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        if($player !== null and MyPlot::getInstance()->isLevelLoaded($player->getPosition()->getWorld()->getFolderName()) and (count(MyPlot::$borders) > 0))
            return new BorderForm();
        return null;
    }
}