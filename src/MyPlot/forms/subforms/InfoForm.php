<?php
declare(strict_types=1);
namespace MyPlot\forms\subforms;

use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;

class InfoForm extends SimpleMyPlotForm {
	public function __construct(Player $player) {
        if(!isset($this->plot))
            $this->plot = MyPlot::getInstance()->getPlotByPosition($player->getPosition());
        if(!isset($this->plot))
            return;

        if (MyPlot::getInstance()->getServer()->getPlayerExact($this->plot->owner)) {
            $owner = $this->plot->owner . " §a(Online)";
        } else {
            $owner = $this->plot->owner . " §c(Offline)";
        }
        $helpers = implode(", ", $this->plot->helpers);
        $denied = implode(", ", $this->plot->denied);
        if (!$this->plot->getFlag("description")) {
            $description = "";
        } else {
            $description = $this->plot->getFlag("description");
        }
		parent::__construct(
		    MyPlot::getInstance()->getLanguage()->translateString("info.title"),
            MyPlot::getInstance()->getLanguage()->translateString("info.content", [$this->plot, $owner, $description, $this->plot->name, $helpers, $denied]),
            [],
            function(Player $submitter, int $selected) : void {},
            function () : void {}
        );
	}
}
