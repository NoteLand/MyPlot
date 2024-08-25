<?php

declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\MyPlotForm;
use MyPlot\forms\subforms\ChatForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\Flags;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ChatSubCommand extends SubCommand
{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.chat");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args): bool{
	    $plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notinplot"));
			return true;
		}
		if(empty($args)) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.define.message"));
			return false;
		}
		if ($args[0] === "toggle") {
            if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.chat")) {
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
                return true;
            }
		    if ($plot->getFlag(Flags::CHAT)) {
		        $plot->setFlag(Flags::CHAT, false);
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.toggle_off"));
            } else {
                $plot->setFlag(Flags::CHAT, true);
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.toggle_on"));
            }
		    $this->getPlugin()->getProvider()->savePlot($plot);
		    return true;
        }

		$message = implode(" ", $args);

		$players = $this->getPlugin()->getServer()->getOnlinePlayers();
		foreach ($players as $player) {
			$playerplot = $this->getPlugin()->getPlotByPosition($player->getPosition());
			if ($playerplot !== null) {
				if ($playerplot === $plot) {
					$player->sendMessage($this->translateString("chat.format", [$sender->getName(), $message]));
				}
			}
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
        if($player !== null and $this->getPlugin()->getPlotByPosition($player->getPosition()) instanceof Plot)
            return new ChatForm($player);
		return null;
	}
}