<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\forms\subforms\DescriptionForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DescriptionSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender) : bool {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.description");
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
        $plot = $this->getOwningPlugin()->getPlotByPosition($sender->getPosition());
        if($plot === null) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
            return true;
        }
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.description")) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
            return true;
        }
        if ($args[0] === "remove") {
            $plot->removeFlag("description");
        } else {
            $plot->setFlag("description", implode(" ", $args));
        }
        $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("description.success"));
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        if($player !== null and $this->getOwningPlugin()->getPlotByPosition($player->getPosition()) instanceof Plot)
            return new DescriptionForm($player);
        return null;
    }
}