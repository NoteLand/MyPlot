<?php


namespace MyPlot\subcommand;


use MyPlot\forms\MyPlotForm;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class RandSubCommand extends SubCommand
{

    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.auto");
    }

    /**
     * @inheritDoc
     */
    public function execute(CommandSender $sender, array $args): bool
    {
        // TODO: Implement execute() method.
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        return null;
    }
}