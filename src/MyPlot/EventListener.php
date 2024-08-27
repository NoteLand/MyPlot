<?php
declare(strict_types=1);

namespace MyPlot;

use JsonException;
use MyPlot\events\MyPlotBlockEvent;
use MyPlot\events\MyPlotBorderChangeEvent;
use MyPlot\events\MyPlotPlayerEnterPlotEvent;
use MyPlot\events\MyPlotPlayerLeavePlotEvent;
use MyPlot\events\MyPlotPvpEvent;
use MyPlot\utils\Flags;
use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Liquid;
use pocketmine\block\Trapdoor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Egg;
use pocketmine\entity\projectile\Snowball;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityTrampleFarmlandEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class EventListener implements Listener {

	/**
	 * EventListener constructor.
	 *
	 * @param MyPlot $plugin
	 */
	public function __construct(
		private readonly MyPlot $plugin
	) {}

	/**
	 * @priority LOWEST
	 *
	 * @param WorldLoadEvent $event
	 *
	 * @throws JsonException
	 */
	public function onLevelLoad(WorldLoadEvent $event) : void {
		if (file_exists($this->plugin->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $event->getWorld()->getFolderName() . ".yml")) {
			$this->plugin->getLogger()->debug("MyPlot level " . $event->getWorld()->getFolderName() . " loaded!");
			$options = $event->getWorld()->getProvider()->getWorldData()->getGeneratorOptions();
			$settings = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
			$levelName = $event->getWorld()->getFolderName();
			$default = array_filter((array) $this->plugin->getConfig()->get("DefaultWorld", []), function ($key) : bool {
				return !in_array($key, ["PlotSize", "GroundHeight", "RoadWidth", "RoadBlock", "WallBlock", "PlotFloorBlock", "PlotFillBlock", "BottomBlock"], true);
			}, ARRAY_FILTER_USE_KEY);
			$config = new Config($this->plugin->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $levelName . ".yml", Config::YAML, $default);
			foreach (array_keys($default) as $key) {
				$settings[$key] = $config->get((string) $key);
			}
			$this->plugin->addLevelSettings($levelName, new PlotLevelSettings($levelName, $settings));

			if ($this->plugin->getConfig()->get('AllowFireTicking', false) === false) {
				$event->getWorld()->removeRandomTickedBlock(VanillaBlocks::FIRE());
			}
		}
	}

	/**
	 * @priority MONITOR
	 *
	 * @param WorldUnloadEvent $event
	 */
	public function onLevelUnload(WorldUnloadEvent $event) : void {
		if ($event->isCancelled()) {
			return;
		}
		$levelName = $event->getWorld()->getFolderName();
		if ($this->plugin->unloadLevelSettings($levelName)) {
			$this->plugin->getLogger()->debug("Level " . $event->getWorld()->getFolderName() . " unloaded!");
		}
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param BlockPlaceEvent $event
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void {
		/**
		 * @var int   $x
		 * @var int   $y
		 * @var int   $z
		 * @var Block $block
		 */
		foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			$ev = new BlockBreakEvent($event->getPlayer(), $block, $event->getItem());
			$this->onEventOnBlock($ev, $event->getPlayer(), $block);
			if($ev->isCancelled()) {
				$event->cancel();
				break;
			}
		}
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param BlockBreakEvent $event
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void {
		$this->onEventOnBlock($event, $event->getPlayer(), $event->getBlock());
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param PlayerInteractEvent $event
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		$this->onEventOnBlock($event, $event->getPlayer(), $event->getBlock());
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param SignChangeEvent $event
	 */
	public function onSignChange(SignChangeEvent $event) : void {
		$this->onEventOnBlock($event, $event->getPlayer(), $event->getBlock());
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param PlayerBucketEmptyEvent $event
	 */
	public function onPlayerBucketEmpty(PlayerBucketEmptyEvent $event) : void {
		$this->onEventOnBlock($event, $event->getPlayer(), $event->getBlockClicked());
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param PlayerBucketFillEvent $event
	 */
	public function onPlayerBucketFill(PlayerBucketFillEvent $event) : void {
		$this->onEventOnBlock($event, $event->getPlayer(), $event->getBlockClicked());
	}

	/**
	 * @param BlockPlaceEvent|BlockBreakEvent|PlayerInteractEvent|SignChangeEvent|PlayerBucketEmptyEvent|PlayerBucketFillEvent $event
	 * @param Player                                                                                                           $player
	 * @param Block                                                                                                            $block
	 */
	private function onEventOnBlock(BlockPlaceEvent|BlockBreakEvent|PlayerInteractEvent|SignChangeEvent|PlayerBucketEmptyEvent|PlayerBucketFillEvent $event, Player $player, Block $block) : void {
		if (!$block->getPosition()->isValid())
			return;
		$levelName = $block->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;

		$username = $player->getName();
		if (($plot = $this->plugin->getPlotByRoadPosition($block->getPosition())) !== null) {
			$ev = new MyPlotBlockEvent($plot, $block, $player, $event);
			if ($event->isCancelled())
				$ev->cancel();
			$ev->call();
			if (!$ev->isCancelled()) {
				if ($player->hasPermission("myplot.admin.build.plot"))
					return;
				if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
					return;
				if (
					($block instanceof Door || $block instanceof Trapdoor || $block instanceof FenceGate) &&
					$plot->getFlag(Flags::INTERACT)
				) {
					return;
				}
				if (($event instanceof BlockBreakEvent) and $plot->getFlag(Flags::BREAK))
					return;
				if (($event instanceof BlockBreakEvent) and $plot->getFlag(Flags::PLACE))
					return;
			}
		} else if (($plot = $this->plugin->getPlotBorderingPosition($block->getPosition())) !== null) {
			if ($this->plugin->getLevelSettings($levelName)->editBorderBlocks) {
				$ev = new MyPlotBorderChangeEvent($plot, $block, $player, $event);
				if ($event->isCancelled())
					$ev->cancel();
				$ev->call();
				if (!$ev->isCancelled()) {
					if ($player->hasPermission("myplot.admin.build.plot"))
						return;
					if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
						return;
				}
			}
		} else if ($player->hasPermission("myplot.admin.build.road"))
			return;
		$event->cancel();
		$this->plugin->getLogger()->debug("Block placement/break/interaction of {$block->getName()} was cancelled at " . $block->getPosition()->__toString());
	}

	/**
	 * @handleCancelled true
	 *
	 * @param BlockBurnEvent $event
	 */
	public function onBlockBurning(BlockBurnEvent $event) : void {
		if (!$event->getBlock()->getPosition()->isValid())
			return;
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition());
		if ($plot === null)
			return;
		if ($plot->getFlag(Flags::BURNING, false))
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param BlockFormEvent $event
	 */
	public function onBlockForm(BlockFormEvent $event) : void {
		if (!$event->getBlock()->getPosition()->isValid())
			return;
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition());
		if ($plot === null)
			return;
		if (
			(($causingPlot = $this->plugin->getPlotByRoadPosition($event->getCausingBlock()->getPosition())) != null) &&
			($causingPlot->isSame($plot) or $causingPlot->getFlag(Flags::FLOWING)) &&
			$plot->getFlag(Flags::FLOWING)
		)
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param BlockGrowEvent $event
	 */
	public function onBlockGrowing(BlockGrowEvent $event) : void {
		if (!$event->getBlock()->getPosition()->isValid())
			return;
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition());
		if ($plot === null)
			return;
		if ($plot->getFlag(Flags::GROWING))
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param EntityTrampleFarmlandEvent $event
	 */
	public function onTrampleFarmlandEvent(EntityTrampleFarmlandEvent $event) : void {
		$player = $event->getEntity();
		if (!($player instanceof Player))
			return;
		if (!$event->getBlock()->getPosition()->isValid())
			return;
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition());
		if ($plot === null)
			return;
		$username = $player->getName();
		if (($plot->owner !== $username) and (!$plot->isHelper($username)) and (!$plot->isHelper("*")) and (!$player->hasPermission("myplot.admin.build.plot")))
			$event->cancel();
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param EntityExplodeEvent $event
	 */
	public function onExplosion(EntityExplodeEvent $event) : void {
		if ($event->isCancelled()) {
			return;
		}
		$levelName = $event->getEntity()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getPosition());
		if ($plot === null) {
			$event->cancel();
			return;
		}
		if (!$plot->getFlag(Flags::EXPLOSION)) {
			$event->cancel();
			return;
		}
		$beginPos = $this->plugin->getPlotPosition($plot);
		$endPos = clone $beginPos;
		$levelSettings = $this->plugin->getLevelSettings($levelName);
		$plotSize = $levelSettings->plotSize;
		$endPos->x += $plotSize;
		$endPos->z += $plotSize;
		$blocks = array_filter($event->getBlockList(), function ($block) use ($beginPos, $endPos) : bool {
			if ($block->getPosition()->getX() >= $beginPos->x and $block->getPosition()->getZ() >= $beginPos->z and $block->getPosition()->getX() < $endPos->x and $block->getPosition()->getZ() < $endPos->z) {
				return true;
			}
			return false;
		});
		$event->setBlockList($blocks);
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param EntityMotionEvent $event
	 */
	public function onEntityMotion(EntityMotionEvent $event) : void {
		if ($event->isCancelled()) {
			return;
		}
		$level = $event->getEntity()->getPosition()->getWorld();
		if (!($level instanceof World))
			return;
		$levelName = $level->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$settings = $this->plugin->getLevelSettings($levelName);
		if ($settings->restrictEntityMovement and !($event->getEntity() instanceof Player)) {
			$event->cancel();
			$this->plugin->getLogger()->debug("Cancelled entity motion on " . $levelName);
		}
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param BlockSpreadEvent $event
	 */
	public function onBlockSpread(BlockSpreadEvent $event) : void {
		if ($event->isCancelled()) {
			return;
		}
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$settings = $this->plugin->getLevelSettings($levelName);

		$sourcePlot = $this->plugin->getPlotByRoadPosition($event->getSource()->getPosition());
		$newBlockInPlot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition()) instanceof Plot;
		$sourceBlockInPlot = $sourcePlot instanceof Plot;

		if ($newBlockInPlot and $sourceBlockInPlot) {
			$spreadIsSamePlot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition())->isSame($this->plugin->getPlotByRoadPosition($event->getSource()->getPosition()));
		} else {
			$spreadIsSamePlot = false;
		}

		if ($event->getSource() instanceof Liquid) {
			if (!$settings->updatePlotLiquids and ($sourceBlockInPlot or $this->plugin->isPositionBorderingPlot($event->getSource()->getPosition()))) {
				$event->cancel();
				$this->plugin->getLogger()->debug("Cancelled {$event->getSource()->getName()} spread on [$levelName]");
			} elseif ($settings->updatePlotLiquids and ($sourceBlockInPlot or $this->plugin->isPositionBorderingPlot($event->getSource()->getPosition())) and (!$newBlockInPlot or !$this->plugin->isPositionBorderingPlot($event->getBlock()->getPosition()) or !$spreadIsSamePlot)) {
				$event->cancel();
				$this->plugin->getLogger()->debug("Cancelled {$event->getSource()->getName()} spread on [$levelName]");
			} elseif ($sourceBlockInPlot && !$sourcePlot->getFlag(Flags::FLOWING))
				$event->cancel();
		} elseif (!$settings->allowOutsidePlotSpread and (!$newBlockInPlot or !$spreadIsSamePlot)) {
			$event->cancel();
			//$this->plugin->getLogger()->debug("Cancelled block spread of {$event->getSource()->getName()} on ".$levelName);
		} elseif ($sourceBlockInPlot && !$sourcePlot->getFlag(Flags::GROWING))
			$event->cancel();
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param PlayerMoveEvent $event
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void {
		$this->onEventOnMove($event->getPlayer(), $event);
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param EntityTeleportEvent $event
	 */
	public function onPlayerTeleport(EntityTeleportEvent $event) : void {
		$entity = $event->getEntity();
		if ($entity instanceof Player) {
			$this->onEventOnMove($entity, $event);
		}
	}

	/**
	 * @param Player                              $player
	 * @param PlayerMoveEvent|EntityTeleportEvent $event
	 */
	private function onEventOnMove(Player $player, $event) : void {
		$levelName = $player->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByRoadPosition($event->getTo());
		$plotFrom = $this->plugin->getPlotByRoadPosition($event->getFrom());
		if ($plot !== null and $plotFrom === null) {
			if (strpos((string) $plot, "-0") != false) {
				return;
			}
			$ev = new MyPlotPlayerEnterPlotEvent($plot, $player);
			if ($event->isCancelled()) $ev->cancel();
			$username = $ev->getPlayer()->getName();
			if ($plot->owner !== $username and ($plot->isDenied($username) or $plot->isDenied("*")) and !$ev->getPlayer()->hasPermission("myplot.admin.denyplayer.bypass")) {
				$ev->cancel();
			}
			$ev->call();
			$ev->isCancelled() ? $event->cancel() : $event->uncancel();
			if ($event->isCancelled()) {
				return;
			}
			if ($plot->getFlag(Flags::FLY)) {
				if (!$player->isCreative())
					$player->setAllowFlight(true);
			}
			if (($value = $plot->getFlag(Flags::WELCOME, "")) != "")
				$player->sendPopup($value);
			if (!(bool) $this->plugin->getConfig()->get("ShowPlotPopup", true))
				return;
			$popup = $this->plugin->getLanguage()->translateString("popup", [TextFormat::GREEN . $plot]);
			$price = TextFormat::GREEN . $plot->price;
			if ($plot->owner !== "") {
				$owner = TextFormat::GREEN . $plot->owner;
				if ($plot->price > 0 and $plot->owner !== $player->getName()) {
					$ownerPopup = $this->plugin->getLanguage()->translateString("popup.forsale", [$owner . TextFormat::WHITE, $price . TextFormat::WHITE]);
				} else {
					$ownerPopup = $this->plugin->getLanguage()->translateString("popup.owner", [$owner . TextFormat::WHITE]);
				}
			} else {
				$ownerPopup = $this->plugin->getLanguage()->translateString("popup.available", [$price . TextFormat::WHITE]);
			}
			$paddingSize = (int) floor((strlen($popup) - strlen($ownerPopup)) / 2);
			$paddingPopup = str_repeat(" ", max(0, -$paddingSize));
			$paddingOwnerPopup = str_repeat(" ", max(0, $paddingSize));
			$popup = TextFormat::WHITE . $paddingPopup . $popup . "\n" . TextFormat::WHITE . $paddingOwnerPopup . $ownerPopup;
			$ev->getPlayer()->sendTip($popup);
		} elseif ($plotFrom !== null and $plot === null) {
			if (strpos((string) $plotFrom, "-0") !== false) {
				return;
			}
			$ev = new MyPlotPlayerLeavePlotEvent($plotFrom, $player);
			if ($event->isCancelled()) $ev->cancel();
			$ev->call();
			$ev->isCancelled() ? $event->cancel() : $event->uncancel();
			if (!$player->isCreative()) {
				$player->setAllowFlight(false);
				$player->setFlying(false);
			}
			if (($value = $plotFrom->getFlag(Flags::FAREWELL, "")) == "")
				return;
			$player->sendTip($value);
		} elseif ($plotFrom !== null and $plot !== null and ($plot->isDenied($player->getName()) or $plot->isDenied("*")) and $plot->owner !== $player->getName() and !$player->hasPermission("myplot.admin.denyplayer.bypass")) {
			$this->plugin->teleportPlayerToPlot($player, $plot);
		}
	}

	/**
	 * @handleCancelled true
	 * @priority LOWEST
	 *
	 * @param EntityDamageByEntityEvent $event
	 */
	public function onEntityDamage(EntityDamageByEntityEvent $event) : void {
		if ($event->isCancelled())
			return;
		$damager = $event->getDamager();
		if (!($damager instanceof Player))
			return;
		$damaged = $event->getEntity();
		$levelName = $damaged->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName)) {
			return;
		}
		$plot = $this->plugin->getPlotByRoadPosition($damaged->getPosition());
		if ($damaged instanceof Player) {
			$settings = $this->plugin->getLevelSettings($levelName);
			if ($plot !== null) {
				$ev = new MyPlotPvpEvent($plot, $damager, $damaged, $event);
				if (!$plot->getFlag(Flags::PVP) and !$damager->hasPermission("myplot.admin.pvp.bypass")) {
					$ev->cancel();
					$this->plugin->getLogger()->debug("Cancelled pvp event in plot " . $plot->X . ";" . $plot->Z . " on level '" . $levelName . "'");
				}
				$ev->call();
				$ev->isCancelled() ? $event->cancel() : $event->uncancel();
				if ($event->isCancelled()) {
					$ev->getAttacker()->sendMessage(TextFormat::RED . $this->plugin->getLanguage()->translateString("pvp.disabled")); // generic message- we dont know if by config or plot
				}
				return;
			}
			if ($damager->hasPermission("myplot.admin.pvp.bypass")) {
				return;
			}
			if ($settings->restrictPVP) {
				$event->cancel();
				$damager->sendMessage(TextFormat::RED . $this->plugin->getLanguage()->translateString("pvp.world"));
				$this->plugin->getLogger()->debug("Cancelled pvp event on " . $levelName);
			}
		} else if (($plot != null) && !$plot->getFlag(Flags::PVE))
			$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param EntityItemPickupEvent $event
	 */
	public function onEntityItemPickup(EntityItemPickupEvent $event) : void {
		if ($event->isCancelled())
			return;
		$player = $event->getEntity();
		if (!($player instanceof Player))
			return;
		$levelName = $event->getOrigin()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$username = $player->getName();
		if (($plot = $this->plugin->getPlotByRoadPosition($event->getOrigin()->getPosition())) == null)
			return;
		if ($plot->getFlag(Flags::ITEM_PICKUP))
			return;
		if ($player->hasPermission("myplot.admin.flags.bypass"))
			return;
		if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param EntityShootBowEvent $event
	 */
	public function onEntityShootBow(EntityShootBowEvent $event) : void {
		if ($event->isCancelled())
			return;
		$entity = $event->getEntity();
		$levelName = $entity->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		if (($plot = $this->plugin->getPlotByRoadPosition($entity->getPosition())) == null) {
			$event->cancel();
			return;
		}
		if (!($entity instanceof Player)) {
			if ($plot->getFlag(Flags::PVE))
				return;
			$event->cancel();
			return;
		}
		$player = $entity;
		if ($plot->getFlag(Flags::PVP))
			return;
		if ($player->hasPermission("myplot.admin.flags.bypass"))
			return;
		$username = $player->getName();
		if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param PlayerDropItemEvent $event
	 */
	public function onPlayerDropItem(PlayerDropItemEvent $event) : void {
		if ($event->isCancelled())
			return;
		$player = $event->getPlayer();
		$levelName = $player->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		$username = $player->getName();
		if (($plot = $this->plugin->getPlotByRoadPosition($player->getPosition())) == null)
			return;
		if ($plot->getFlag(Flags::ITEM_DROP))
			return;
		if ($player->hasPermission("myplot.admin.flags.bypass"))
			return;
		if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param ProjectileLaunchEvent $event
	 */
	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void {
		if ($event->isCancelled())
			return;
		$entity = $event->getEntity();
		$levelName = $entity->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		if (($plot = $this->plugin->getPlotByRoadPosition($entity->getPosition())) == null) {
			$event->cancel();
			return;
		}
		$owningEntity = $entity->getOwningEntity();
		if (!($owningEntity instanceof Player)) {
			if (($owningEntity != null) && $plot->getFlag(Flags::PVE))
				return;
			$event->cancel();
			return;
		}
		$player = $owningEntity;
		if ($player->hasPermission("myplot.admin.flags.bypass"))
			return;
		$username = $player->getName();
		if ($plot->owner === $username || $plot->isHelper($username) || $plot->isHelper("*"))
			return;
		if (
			(
				$entity instanceof Arrow ||
				$entity instanceof Egg ||
				$entity instanceof Snowball ||
				$entity instanceof SplashPotion
			) &&
			$plot->getFlag(Flags::PVP)
		)
			return;
		$event->cancel();
	}

	/**
	 * @handleCancelled true
	 *
	 * @param StructureGrowEvent $event
	 */
	public function onStructureGrow(StructureGrowEvent $event) : void {
		if ($event->isCancelled())
			return;
		$levelName = $event->getBlock()->getPosition()->getWorld()->getFolderName();
		if (!$this->plugin->isLevelLoaded($levelName))
			return;
		if (($plot = $this->plugin->getPlotByRoadPosition($event->getBlock()->getPosition())) == null)
			return;
		if ($plot->getFlag(Flags::GROWING))
			return;
		$event->cancel();
	}
}