<?php


namespace MyPlot\listeners;


use MyPlot\MyPlot;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;

class PlayerChatListener implements Listener
{

    /**
     * @handleCancelled false
     * @priority MONITOR
     *
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event) {
        if ($event->isCancelled()) return;
        $levelName = $event->getPlayer()->getPosition()->getWorld()->getFolderName();
        if(!MyPlot::getInstance()->isLevelLoaded($levelName)) return;
        $plot = MyPlot::getInstance()->getPlotByPosition($event->getPlayer()->getPosition());
        if($plot === null) return;

        $message = $event->getMessage();

        if ($plot->getFlag("chat")) {
            $event->cancel();
            $players = MyPlot::getInstance()->getServer()->getOnlinePlayers();
            foreach ($players as $player) {
                $playerplot = MyPlot::getInstance()->getPlotByPosition($player->getPosition());
                if ($playerplot !== NULL) {
                    if ($playerplot === $plot) {
                        $player->sendMessage(TextFormat::GOLD . MyPlot::getInstance()->getLanguage()->translateString("chat.format", [$player->getName(),TextFormat::WHITE . $message]));
                    }
                }
            }
        }
    }
}