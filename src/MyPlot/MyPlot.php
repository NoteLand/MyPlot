<?php
declare(strict_types=1);
namespace MyPlot;

use muqsit\worldstyler\Selection;
use muqsit\worldstyler\shapes\CommonShape;
use muqsit\worldstyler\shapes\Cuboid;
use muqsit\worldstyler\WorldStyler;
use MyPlot\events\MyPlotClearEvent;
use MyPlot\events\MyPlotCloneEvent;
use MyPlot\events\MyPlotDisposeEvent;
use MyPlot\events\MyPlotGenerationEvent;
use MyPlot\events\MyPlotResetEvent;
use MyPlot\events\MyPlotSettingEvent;
use MyPlot\events\MyPlotTeleportEvent;
use MyPlot\listeners\PlayerChatListener;
use MyPlot\provider\DataProvider;
use MyPlot\provider\EconomyProvider;
use MyPlot\provider\EconomySProvider;
use MyPlot\provider\JSONDataProvider;
use MyPlot\provider\MySQLProvider;
use MyPlot\provider\SQLiteDataProvider;
use MyPlot\provider\SQLiteV2DataProvider;
use MyPlot\provider\YAMLDataProvider;
use MyPlot\task\ChangeBorderTask;
use MyPlot\task\ClearBorderTask;
use MyPlot\task\ClearPlotTask;
use MyPlot\task\MergePlotTast;
use MyPlot\utils\Border;
use MyPlot\utils\Wall;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\BlockFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\lang\Language;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\BiomeArray;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;

class MyPlot extends PluginBase
{
	/** @var MyPlot $instance */
	private static $instance;
	/** @var PlotLevelSettings[] $levels */
	private $levels = [];
	/** @var DataProvider $dataProvider */
	private $dataProvider = null;
	/** @var EconomyProvider $economyProvider */
	private $economyProvider = null;
	/** @var Language $baseLang */
	private $baseLang = null;

    /** @var Border[] $borders */
    public static $borders = [];

    /** @var Wall[] $walls */
	public static $walls = [];

	/** @var string $prefix */
	private static $prefix = "";

	public static function getInstance() : self {
		return self::$instance;
	}

	public static function getPrefix() : string {
	    return self::$prefix;
    }

	/**
	 * Returns the Multi-lang management class
	 *
	 * @api
	 *
	 * @return Language
	 */
	public function getLanguage() : Language {
		return $this->baseLang;
	}

	/**
	 * Returns the fallback language class
	 *
	 * @internal
	 *
	 * @return Language
	 */
	public function getFallBackLang() : Language {
		return new Language(Language::FALLBACK_LANGUAGE, $this->getFile() . "resources/");
	}

	/**
	 * Returns the DataProvider that is being used
	 *
	 * @api
	 *
	 * @return DataProvider
	 */
	public function getProvider() : DataProvider {
		return $this->dataProvider;
	}

	/**
	 * Returns the EconomyProvider that is being used
	 *
	 * @api
	 *
	 * @return EconomyProvider|null
	 */
	public function getEconomyProvider() : ?EconomyProvider {
		return $this->economyProvider;
	}

	/**
	 * Allows setting the economy provider to a custom provider or to null to disable economy mode
	 *
	 * @api
	 *
	 * @param EconomyProvider|null $provider
	 */
	public function setEconomyProvider(?EconomyProvider $provider) : void {
		if($provider === null) {
			$this->getConfig()->set("UseEconomy", false);
			$this->getLogger()->info("Economy mode disabled!");
		}else{
			$this->getLogger()->info("A custom economy provider has been registered. Economy mode now enabled!");
			$this->getConfig()->set("UseEconomy", true);
			$this->economyProvider = $provider;
		}
	}

	/**
	 * Returns a PlotLevelSettings object which contains all the settings of a level
	 *
	 * @api
	 *
	 * @param string $levelName
	 *
	 * @return PlotLevelSettings
	 */
	public function getLevelSettings(string $levelName) : PlotLevelSettings {
		if(!isset($this->levels[$levelName]))
            throw new AssumptionFailedError("Provided level name is not a MyPlot level");
		return $this->levels[$levelName];
	}

	/**
	 * Checks if a plot level is loaded
	 *
	 * @api
	 *
	 * @param string $levelName
	 *
	 * @return bool
	 */
	public function isLevelLoaded(string $levelName) : bool {
		return isset($this->levels[$levelName]);
	}

	/**
	 * Generate a new plot level with optional settings
	 *
	 * @api
	 *
	 * @param string $levelName
	 * @param string $generator
	 * @param mixed[] $settings
	 *
	 * @return bool
	 */
	public function generateLevel(string $levelName, string $generator = MyPlotGenerator::NAME, array $settings = []) : bool {
		$ev = new MyPlotGenerationEvent($levelName, $generator, $settings);
		$ev->call();
		$worldManager = $this->getServer()->getWorldManager();
		if($ev->isCancelled() or $worldManager->isWorldGenerated($levelName)) {
			return false;
		}
		$generator = GeneratorManager::getInstance()->getGenerator($generator)->getGeneratorClass();
		if(count($settings) === 0) {
			$this->getConfig()->reload();
			$settings = $this->getConfig()->get("DefaultWorld", []);
		}
		$default = array_filter((array) $this->getConfig()->get("DefaultWorld", []), function($key) : bool {
			return !in_array($key, ["PlotSize", "GroundHeight", "RoadWidth", "RoadBlock", "WallBlock", "PlotFloorBlock", "PlotFillBlock", "BottomBlock"], true);
		}, ARRAY_FILTER_USE_KEY);
		new Config($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$levelName.".yml", Config::YAML, $default);
        $options = WorldCreationOptions::create()->setGeneratorClass($generator)->setGeneratorOptions(json_encode($settings));
        $return = $worldManager->generateWorld($levelName, $options);
        $world = $worldManager->getWorldByName($levelName);
        if($world !== null)
            $world->setSpawnLocation(new Vector3(0, $this->getConfig()->getNested("DefaultWorld.GroundHeight", 64) + 1,0));
        return $return;
	}

	/**
	 * Saves provided plot if changed
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return bool
	 */
	public function savePlot(Plot $plot) : bool {
		return $this->dataProvider->savePlot($plot);
	}

	/**
	 * Get all the plots a player owns (in a certain level if $levelName is provided)
	 *
	 * @api
	 *
	 * @param string $username
	 * @param string $levelName
	 *
	 * @return Plot[]
	 */
	public function getPlotsOfPlayer(string $username, string $levelName) : array {
		return $this->dataProvider->getPlotsByOwner($username, $levelName);
	}

	/**
	 * Get the next free plot in a level
	 *
	 * @api
	 *
	 * @param string $levelName
	 * @param int $limitXZ
	 *
	 * @return Plot|null
	 */
	public function getNextFreePlot(string $levelName, int $limitXZ = 0) : ?Plot {
		return $this->dataProvider->getNextFreePlot($levelName, $limitXZ);
	}

	/**
	 * Finds the plot at a certain position or null if there is no plot at that position
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return Plot|null
	 */
	public function getPlotByPosition(Position $position) : ?Plot {
		$x = $position->x;
		$z = $position->z;
		$levelName = $position->getWorld()->getFolderName();
		if(!$this->isLevelLoaded($levelName))
			return null;

		$plotLevel = $this->getLevelSettings($levelName);
		$plotSize = $plotLevel->plotSize;
		$roadWidth = $plotLevel->roadWidth;
		$totalSize = $plotSize + $roadWidth;
		if($x >= 0) {
			$X = (int) floor($x / $totalSize);
			$difX = $x % $totalSize;
		}else{
			$X = (int) ceil(($x - $plotSize + 1) / $totalSize);
			$difX = abs(($x - $plotSize + 1) % $totalSize);
		}
		if($z >= 0) {
			$Z = (int) floor($z / $totalSize);
			$difZ = $z % $totalSize;
		}else{
			$Z = (int) ceil(($z - $plotSize + 1) / $totalSize);
			$difZ = abs(($z - $plotSize + 1) % $totalSize);
		}
		if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
			return null;
		}
		return $this->dataProvider->getPlot($levelName, $X, $Z);
	}

	public function getPlotByRoadPosition(Position $position) : ?Plot {
		$levelName = $position->getWorld()->getFolderName();
		$plotLevelSettings = $this->getLevelSettings($levelName);
		if (($plot = $this->getPlotByPosition($position)) !== null)
			return $plot;
		$plotToCheck1 = $this->getPlotByPosition(new Position($position->x + $plotLevelSettings->roadWidth, $position->y, $position->z, $position->getWorld()));
		$plotToCheck2 = $this->getPlotByPosition(new Position($position->x - $plotLevelSettings->roadWidth, $position->y, $position->z, $position->getWorld()));
		if ($plotToCheck1 !== null && $plotToCheck2 !== null) {
			if ($plotToCheck1->isMerged("westmerge") and $plotToCheck2->isMerged("eastmerge"))
				return $plotToCheck1;
			return null;
		}
		$plotToCheck1 = $this->getPlotByPosition(new Position($position->x, $position->y, $position->z + $plotLevelSettings->roadWidth, $position->getWorld()));
		$plotToCheck2 = $this->getPlotByPosition(new Position($position->x, $position->y, $position->z - $plotLevelSettings->roadWidth, $position->getWorld()));
		if ($plotToCheck1 !== null && $plotToCheck2 !== null) {
			if ($plotToCheck1->isMerged("northmerge") and $plotToCheck2->isMerged("southmerge"))
				return $plotToCheck1;
			return null;
		}
		return null;
	}

	public function mergePlot(Plot $start, Plot $ende, string $direction) : bool {
		if(!$this->isLevelLoaded($start->levelName)) {
			return false;
		}
		$start->mergeData($ende);
		$ende->mergeData($start);
		$this->getScheduler()->scheduleTask(new MergePlotTast($this, $start, $direction));
		return true;
	}

	/**
	 * Get the begin position of a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return Position
	 */
	public function getPlotPosition(Plot $plot) : Position {
		$plotLevel = $this->getLevelSettings($plot->levelName);
		$plotSize = $plotLevel->plotSize;
		$roadWidth = $plotLevel->roadWidth;
		$totalSize = $plotSize + $roadWidth;
		$x = $totalSize * $plot->X;
		$z = $totalSize * $plot->Z;
		$level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		return new Position($x, $plotLevel->groundHeight, $z, $level);
	}

	/**
	 * Detects if the given position is bordering a plot
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return bool
	 */
	public function isPositionBorderingPlot(Position $position) : bool {
		if(!$position->isValid())
			return false;
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
			$pos = $position->getSide($i);
			$x = $pos->x;
			$z = $pos->z;
			$levelName = $pos->getWorld()->getFolderName();

			if(!$this->isLevelLoaded($levelName))
				return false;

			$plotLevel = $this->getLevelSettings($levelName);
			$plotSize = $plotLevel->plotSize;
			$roadWidth = $plotLevel->roadWidth;
			$totalSize = $plotSize + $roadWidth;
			if($x >= 0) {
				$difX = $x % $totalSize;
			}else{
				$difX = abs(($x - $plotSize + 1) % $totalSize);
			}
			if($z >= 0) {
				$difZ = $z % $totalSize;
			}else{
				$difZ = abs(($z - $plotSize + 1) % $totalSize);
			}
			if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
				continue;
			}
			return true;
		}
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
			for($n = Facing::NORTH; $n <= Facing::EAST; ++$n) {
				if($i === $n or Facing::opposite($i) === $n)
					continue;
				$pos = $position->getSide($i)->getSide($n);
				$x = $pos->x;
				$z = $pos->z;
				$levelName = $pos->getWorld()->getFolderName();

				$plotLevel = $this->getLevelSettings($levelName);
				$plotSize = $plotLevel->plotSize;
				$roadWidth = $plotLevel->roadWidth;
				$totalSize = $plotSize + $roadWidth;
				if($x >= 0) {
					$difX = $x % $totalSize;
				}else{
					$difX = abs(($x - $plotSize + 1) % $totalSize);
				}
				if($z >= 0) {
					$difZ = $z % $totalSize;
				}else{
					$difZ = abs(($z - $plotSize + 1) % $totalSize);
				}
				if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
					continue;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieves the plot adjacent to teh given position
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return Plot|null
	 */
	public function getPlotBorderingPosition(Position $position) : ?Plot {
		if(!$position->isValid())
			return null;
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
			$pos = $position->getSide($i);
			$x = $pos->x;
			$z = $pos->z;
			$levelName = $pos->getWorld()->getFolderName();

			if(!$this->isLevelLoaded($levelName))
				return null;

			$plotLevel = $this->getLevelSettings($levelName);
			$plotSize = $plotLevel->plotSize;
			$roadWidth = $plotLevel->roadWidth;
			$totalSize = $plotSize + $roadWidth;
			if($x >= 0) {
				$X = (int) floor($x / $totalSize);
				$difX = $x % $totalSize;
			}else{
				$X = (int) ceil(($x - $plotSize + 1) / $totalSize);
				$difX = abs(($x - $plotSize + 1) % $totalSize);
			}
			if($z >= 0) {
				$Z = (int) floor($z / $totalSize);
				$difZ = $z % $totalSize;
			}else{
				$Z = (int) ceil(($z - $plotSize + 1) / $totalSize);
				$difZ = abs(($z - $plotSize + 1) % $totalSize);
			}
			if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
				continue;
			}
			return $this->dataProvider->getPlot($levelName, $X, $Z);
		}
		return null;
	}

	/**
	 * Returns the AABB of the plot area
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return AxisAlignedBB
	 */
	public function getPlotBB(Plot $plot) : AxisAlignedBB {
		$plotLevel = $this->getLevelSettings($plot->levelName);
		$pos = $this->getPlotPosition($plot);
		$plotSize = $plotLevel->plotSize-1;

		return new AxisAlignedBB(
			min($pos->x, $pos->x + $plotSize),
			0,
			min($pos->z, $pos->z + $plotSize),
			max($pos->x, $pos->x + $plotSize),
			$pos->getWorld()->getMaxY(),
			max($pos->z, $pos->z + $plotSize)
		);
	}

	/**
	 * Teleport a player to a plot
	 *
	 * @api
	 *
	 * @param Player $player
	 * @param Plot $plot
	 * @param bool $center
	 *
	 * @return bool
	 */
    public function teleportPlayerToPlot(Player $player, Plot $plot, bool $center = false) : bool {
        $ev = new MyPlotTeleportEvent($plot, $player, $center);
        $ev->call();
        if($ev->isCancelled()) {
            return false;
        }
        if($center)
            return $this->teleportMiddle($player, $plot);
        $plotLevel = $this->getLevelSettings($plot->levelName);
        if($plotLevel === null)
            return false;

        if ($plot->getFlag("spawn") !== false) {
            $spawn = explode(";", $plot->getFlag("spawn"));
            if (count($spawn) === 3 and is_numeric($spawn[0]) and is_numeric($spawn[1]) and is_numeric($spawn[2])) {
                $spawn = new Position(intval($spawn[0]), intval($spawn[1]), intval($spawn[2]), $this->getServer()->getWorldManager()->getWorldByName($plot->levelName));
                return $player->teleport($spawn);
            }
        }

        $pos = $this->getPlotPosition($plot);
        $pos->x += floor($plotLevel->plotSize / 2);
        $pos->y += 1.5;
        $pos->z -= 1;
        return $player->teleport($pos);
    }

	/**
	 * Claims a plot in a players name
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param string $claimer
	 * @param string $plotName
	 *
	 * @return bool
	 */
	public function claimPlot(Plot $plot, string $claimer, string $plotName = "") : bool {
		$newPlot = clone $plot;
		$newPlot->owner = $claimer;
		$newPlot->price = 0.0;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		if($plotName !== "") {
			$this->renamePlot($plot, $plotName);
		}
        $plotsquared = new Config($this->getDataFolder() . "plotsquaredpm.yml");
        $block = explode(':', $plotsquared->get("ClaimBorder", "44:6"));
        if (count($block) === 2 and is_numeric($block[0]) and is_numeric($block[1])) {
            $block = BlockFactory::getInstance()->get((int) $block[0], (int) $block[1]);
            $block = new Border("Border", $block, "myplot.border.default");
            MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeBorderTask($plot, $block));
        }
		return $this->savePlot($plot);
	}

	/**
	 * Renames a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param string $newName
	 *
	 * @return bool
	 */
	public function renamePlot(Plot $plot, string $newName = "") : bool {
		$newPlot = clone $plot;
		$newPlot->name = $newName;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * Clones a plot to another location
	 *
	 * @api
	 *
	 * @param Plot $plotFrom
	 * @param Plot $plotTo
	 *
	 * @return bool
	 */
	public function clonePlot(Plot $plotFrom, Plot $plotTo) : bool {
		$styler = $this->getServer()->getPluginManager()->getPlugin("WorldStyler");
		if(!$styler instanceof WorldStyler) {
			return false;
		}
		if(!$this->isLevelLoaded($plotFrom->levelName) or !$this->isLevelLoaded($plotTo->levelName)) {
			return false;
		}
		$aabb = $this->getPlotBB($plotTo);
		$world = $this->getServer()->getWorldManager()->getWorldByName($plotTo->levelName);
		foreach($this->getPlotChunks($plotTo) as $hash) {
		    World::getXZ($hash, $x, $z);
			foreach($world->getChunkEntities($x, $z) as $entity) {
				if($aabb->isVectorInXZ($entity->getPosition()->asVector3())) {
					if($entity instanceof Player){
						$this->teleportPlayerToPlot($entity, $plotTo);
					}
				}
			}
		}
		$ev = new MyPlotCloneEvent($plotFrom, $plotTo);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plotFrom = $ev->getPlot();
		$plotTo = $ev->getClonePlot();
		if(!$this->isLevelLoaded($plotFrom->levelName) or !$this->isLevelLoaded($plotTo->levelName)) {
			return false;
		}
		$plotLevel = $this->getLevelSettings($plotFrom->levelName);
		$plotSize = $plotLevel->plotSize-1;
		$plotBeginPos = $this->getPlotPosition($plotFrom);
		$level = $plotBeginPos->getWorld();
		$plotBeginPos = $plotBeginPos->subtract(1, 0, 1);
		$plotBeginPos->y = 0;
		$plugin = $this;
		$selection = $styler->getSelection(99997) ?? new Selection(99997);
		$selection->setPosition(1, $plotBeginPos);
		$vec2 = new Vector3($plotBeginPos->x + $plotSize + 1, $level->getMaxY() - 1, $plotBeginPos->z + $plotSize + 1);
		$selection->setPosition(2, $vec2);
		$cuboid = Cuboid::fromSelection($selection);
		//$cuboid = $cuboid->async(); // do not use async because WorldStyler async is very broken right now
		$cuboid->copy($level, $vec2, function (float $time, int $changed) use ($plugin) : void {
			$plugin->getLogger()->debug(TF::GREEN . 'Copied ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's to the MyPlot clipboard.');
		});

		$plotLevel = $this->getLevelSettings($plotTo->levelName);
		$plotSize = $plotLevel->plotSize-1;
		$plotBeginPos = $this->getPlotPosition($plotTo);
		$level = $plotBeginPos->getWorld();
		$plotBeginPos = $plotBeginPos->subtract(1, 0, 1);
		$plotBeginPos->y = 0;
		$selection->setPosition(1, $plotBeginPos);
		$vec2 = new Vector3($plotBeginPos->x + $plotSize + 1, $level->getMaxY() - 1, $plotBeginPos->z + $plotSize + 1);
		$selection->setPosition(2, $vec2);
		$commonShape = CommonShape::fromSelection($selection);
		//$commonShape = $commonShape->async(); // do not use async because WorldStyler async is very broken right now
		$commonShape->paste($level, $vec2, true, function (float $time, int $changed) use ($plugin) : void {
			$plugin->getLogger()->debug(TF::GREEN . 'Pasted ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's from the MyPlot clipboard.');
		});
		$styler->removeSelection(99997);
		foreach($this->getPlotChunks($plotTo) as $hash) {
		    World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $world->getChunk($x, $z) ?? new Chunk([], BiomeArray::fill(BiomeIds::PLAINS), false));
		}
		return true;
	}

	/**
	 * Reset all the blocks inside a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param int $maxBlocksPerTick
	 *
	 * @return bool
	 */
	public function clearPlot(Plot $plot, int $maxBlocksPerTick = 256) : bool {
		$ev = new MyPlotClearEvent($plot, $maxBlocksPerTick);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		if(!$this->isLevelLoaded($plot->levelName)) {
			return false;
		}
		$maxBlocksPerTick = $ev->getMaxBlocksPerTick();
		$level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		if($level === null)
			return false;
		foreach($level->getEntities() as $entity) {
			if($this->getPlotBB($plot)->isVectorInXZ($entity->getPosition()->asVector3())) {
				if(!$entity instanceof Player) {
					$entity->flagForDespawn();
				}else{
					$this->teleportPlayerToPlot($entity, $plot);
				}
			}
		}
		if((bool) $this->getConfig()->get("FastClearing", false)) {
			$styler = $this->getServer()->getPluginManager()->getPlugin("WorldStyler");
			if(!$styler instanceof WorldStyler) {
				return false;
			}
			$plotLevel = $this->getLevelSettings($plot->levelName);
			$plotSize = $plotLevel->plotSize-1;
			$plotBeginPos = $this->getPlotPosition($plot);
			$plugin = $this;
			// Above ground
			$selection = $styler->getSelection(99998) ?? new Selection(99998);
			$plotBeginPos->y = $plotLevel->groundHeight+1;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, World::Y_MAX, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->getWorld(), VanillaBlocks::AIR(), function (float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Ground Surface
			$selection = $styler->getSelection(99998) ?? new Selection(99998);
			$plotBeginPos->y = $plotLevel->groundHeight;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, $plotLevel->groundHeight, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->getWorld(), $plotLevel->plotFloorBlock, function (float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Ground
			$selection = $styler->getSelection(99998) ?? new Selection(99998);
			$plotBeginPos->y = 1;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, $plotLevel->groundHeight-1, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->getWorld(), $plotLevel->plotFillBlock, function (float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Bottom of world
			$selection = $styler->getSelection(99998) ?? new Selection(99998);
			$plotBeginPos->y = 0;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, 0, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->getWorld(), $plotLevel->bottomBlock, function (float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
            foreach($this->getPlotChunks($plot) as $hash) {
                World::getXZ($hash, $x, $z);
                $level->setChunk($x, $z, $level->getChunk($x, $z) ?? new Chunk([], BiomeArray::fill(BiomeIds::PLAINS), false));
            }
			$this->getScheduler()->scheduleDelayedTask(new ClearBorderTask($this, $plot), 1);
			return true;
		}
		$this->getScheduler()->scheduleTask(new ClearPlotTask($this, $plot, $maxBlocksPerTick));
		return true;
	}

	/**
	 * Delete the plot data
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return bool
	 */
	public function disposePlot(Plot $plot) : bool {
		$ev = new MyPlotDisposeEvent($plot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$resetBorder = self::getInstance()->getLevelSettings($plot->levelName)->wallBlock;
        $resetBorder = new Border("Border", $resetBorder, "myplot.border.default");
        MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeBorderTask($plot, $resetBorder));
		return $this->dataProvider->deletePlot($plot);
	}

	/**
	 * Clear and dispose a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param int $maxBlocksPerTick
	 *
	 * @return bool
	 */
	public function resetPlot(Plot $plot, int $maxBlocksPerTick = 256) : bool {
		$ev = new MyPlotResetEvent($plot);
		$ev->call();
		if($ev->isCancelled())
			return false;
		if($this->disposePlot($plot)) {
			return $this->clearPlot($plot, $maxBlocksPerTick);
		}
		return false;
	}

	/**
	 * Changes the biome of a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param Biome $biome
	 *
	 * @return bool
	 */
	public function setPlotBiome(Plot $plot, Biome $biome) : bool {
		$newPlot = clone $plot;
		$newPlot->biome = str_replace(" ", "_", strtoupper($biome->getName()));
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		if(defined(Biome::class."::".$plot->biome) and is_int(constant(Biome::class."::".$plot->biome))) {
			$biome = constant(Biome::class."::".$plot->biome);
		}else{
			$biome = BiomeIds::PLAINS;
		}
		$biome = BiomeRegistry::getInstance()->getBiome($biome);
		if(!$this->isLevelLoaded($plot->levelName))
			return false;
		$plotLevel = $this->getLevelSettings($plot->levelName);
		$level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		if($level === null)
			return false;
		$chunks = $this->getPlotChunks($plot);
		foreach($chunks as $hash) {
		    World::getXZ($hash, $chunkX, $chunkZ);
		    $chunk = $level->getChunk($chunkX, $chunkZ) ?? new Chunk([], BiomeArray::fill(BiomeIds::PLAINS), false);
			for($x = 0; $x < 16; ++$x) {
				for($z = 0; $z < 16; ++$z) {
					$chunkPlot = $this->getPlotByPosition(new Position(($chunkX << Chunk::COORD_BIT_SIZE) + $x, $plotLevel->groundHeight, ($chunkZ << Chunk::COORD_BIT_SIZE) + $z, $level));
					if($chunkPlot instanceof Plot and $chunkPlot->isSame($plot)) {
						$chunk->setBiomeId($x, $z, $biome->getId());
					}
				}
			}
			$level->setChunk($chunkX, $chunkZ, $chunk);
		}
		return $this->savePlot($plot);
	}

	public function setPlotPvp(Plot $plot, bool $pvp) : bool {
		$newPlot = clone $plot;
		$newPlot->pvp = $pvp;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	public function addPlotHelper(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		if (!$newPlot->addHelper($player)) {
		    $ev->cancel();
        }
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	public function removePlotHelper(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
        if (!$newPlot->removeHelper($player)) {
            $ev->cancel();
        }
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	public function addPlotDenied(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
        if (!$newPlot->denyPlayer($player)) {
            $ev->cancel();
        }
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	public function removePlotDenied(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
        if (!$newPlot->unDenyPlayer($player)) {
            $ev->cancel();
        }
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * Assigns a price to a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param float $price
	 *
	 * @return bool
	 */
	public function sellPlot(Plot $plot, float $price) : bool {
		if($this->getEconomyProvider() === null or $price < 0)
			return false;

		$newPlot = clone $plot;
		$newPlot->price = $price;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		return $this->savePlot($plot);
	}

	/**
	 * Resets the price, adds the money to the player's account and claims a plot in a players name
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function buyPlot(Plot $plot, Player $player) : bool {
		if($this->getEconomyProvider() === null or !$this->getEconomyProvider()->reduceMoney($player, $plot->price) or !$this->getEconomyProvider()->addMoney($this->getServer()->getOfflinePlayer($plot->owner), $plot->price))
			return false;

		$newPlot = clone $plot;
		$newPlot->owner = $player->getName();
		$newPlot->helpers = [];
		$newPlot->denied = [];
		$newPlot->price = 0.0;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		return $this->savePlot($plot);
	}

	/**
	 * Returns the PlotLevelSettings of all the loaded levels
	 *
	 * @api
	 *
	 * @return PlotLevelSettings[]
	 */
	public function getPlotLevels() : array {
		return $this->levels;
	}

	/**
	 * Returns the Chunks contained in a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return int[]
	 */
	public function getPlotChunks(Plot $plot) : array {
		if(!$this->isLevelLoaded($plot->levelName))
			return [];
		$plotLevel = $this->getLevelSettings($plot->levelName);
		$level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		if($level === null)
			return [];
		$pos = $this->getPlotPosition($plot);
		$plotSize = $plotLevel->plotSize;
		$xMax = ($pos->x + $plotSize) >> 4;
		$zMax = ($pos->z + $plotSize) >> 4;
		$chunks = [];
		for($x = $pos->x >> Chunk::COORD_BIT_SIZE; $x <= $xMax; $x++) {
			for($z = $pos->z >> Chunk::COORD_BIT_SIZE; $z <= $zMax; $z++) {
				$chunks[] = World::chunkHash($x, $z);
			}
		}
		return $chunks;
	}

	/**
	 * Get the maximum number of plots a player can claim
	 *
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return int
	 */
	public function getMaxPlotsOfPlayer(Player $player) : int {
		if($player->hasPermission("myplot.claimplots.unlimited"))
			return PHP_INT_MAX;
		$player->recalculatePermissions();
		$perms = $player->getEffectivePermissions();
		$perms = array_filter($perms, function(string $name) : bool {
            return str_starts_with($name, "myplot.claimplots.") and !str_contains($name, "unlimited");
        }, ARRAY_FILTER_USE_KEY);
		if(count($perms) === 0)
			return 0;
		krsort($perms, SORT_FLAG_CASE | SORT_NATURAL);
		/**
		 * @var string $name
		 * @var Permission $perm
		 */
		foreach($perms as $name => $perm) {
			$maxPlots = substr($name, 18);
			if(is_numeric($maxPlots)) {
				return (int) $maxPlots;
			}
		}
		return 0;
	}

	/**
	 * Finds the exact center of the plot at ground level
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return Position|null
	 */
	public function getPlotMid(Plot $plot) : ?Position {
		if(!$this->isLevelLoaded($plot->levelName))
			return null;
		$plotLevel = $this->getLevelSettings($plot->levelName);
		$plotSize = $plotLevel->plotSize;
		$pos = $this->getPlotPosition($plot);
		$pos = new Position($pos->x + ($plotSize / 2), $pos->y + 1, $pos->z + ($plotSize / 2), $pos->getWorld());
		return $pos;
	}

	/**
	 * Teleports the player to the exact center of the plot at nearest open space to the ground level
	 *
	 * @internal
	 *
	 * @param Plot $plot
	 * @param Player $player
	 *
	 * @return bool
	 */
	private function teleportMiddle(Player $player, Plot $plot) : bool {
		$mid = $this->getPlotMid($plot);
		if($mid === null) {
			return false;
		}
		return $player->teleport($mid);
	}

	/* -------------------------- Non-API part -------------------------- */
	public function onLoad() : void {
		self::$instance = $this;
		$this->getLogger()->debug(TF::BOLD . "Loading Configs");
		$this->reloadConfig();
		@mkdir($this->getDataFolder() . "worlds");
		$this->getLogger()->debug(TF::BOLD . "Loading MyPlot Generator");
		GeneratorManager::getInstance()->addGenerator(MyPlotGenerator::class, "myplot", fn() => null, true);
		$this->getLogger()->debug(TF::BOLD . "Loading Languages");
		// Loading Languages
		/** @var string $lang */
		$lang = $this->getConfig()->get("Language", Language::FALLBACK_LANGUAGE);
		if((bool) $this->getConfig()->get("Custom Messages", false)) {
			if(!file_exists($this->getDataFolder()."eng.ini")) {
				$this->saveResource("eng.ini", true);
				$this->getLogger()->debug("Custom Language ini created");
			}
			$this->baseLang = new Language("eng", $this->getDataFolder());
		}else{
			if(file_exists($this->getDataFolder()."eng.ini")) {
				unlink($this->getDataFolder()."eng.ini");
				$this->getLogger()->debug("Custom Language ini deleted");
			}
			$this->baseLang = new Language($lang, $this->getFile() . "resources/");
		}
		$this->getLogger()->debug(TF::BOLD . "Loading Data Provider settings");
		// Initialize DataProvider
		/** @var int $cacheSize */
		$cacheSize = $this->getConfig()->get("PlotCacheSize", 256);
		$dataProvider = $this->getConfig()->get("DataProvider", "sqlite3");
		if(!is_string($dataProvider))
			$this->dataProvider = new JSONDataProvider($this, $cacheSize);
		else
			try {
				switch(strtolower($dataProvider)) {
					case "mysqli":
					case "mysql":
						if(extension_loaded("mysqli")) {
							$settings = (array) $this->getConfig()->get("MySQLSettings");
							$this->dataProvider = new MySQLProvider($this, $cacheSize, $settings);
						}else {
							$this->getLogger()->warning("MySQLi is not installed in your php build! JSON will be used instead.");
							$this->dataProvider = new JSONDataProvider($this, $cacheSize);
						}
					break;
					case "yaml":
						if(extension_loaded("yaml")) {
							$this->dataProvider = new YAMLDataProvider($this, $cacheSize);
						}else {
							$this->getLogger()->warning("YAML is not installed in your php build! JSON will be used instead.");
							$this->dataProvider = new JSONDataProvider($this, $cacheSize);
						}
					break;
					case "sqlite3":
					case "sqlite":
						if(extension_loaded("sqlite3")) {
							$this->dataProvider = new SQLiteDataProvider($this, $cacheSize);
						}else {
							$this->getLogger()->warning("SQLite3 is not installed in your php build! JSON will be used instead.");
							$this->dataProvider = new JSONDataProvider($this, $cacheSize);
						}
					break;
					case "sqliteV2":
						if(extension_loaded("sqlite3")) {
							$this->getLogger()->warning("SQLiteV2 is experimental!");
							$this->dataProvider = new SQLiteV2DataProvider($this, $cacheSize);
						}else {
							$this->getLogger()->warning("SQLite3 is not installed in your php build! JSON will be used instead.");
							$this->dataProvider = new JSONDataProvider($this, $cacheSize);
						}
						break;
					case "json":
					default:
						$this->dataProvider = new JSONDataProvider($this, $cacheSize);
					break;
				}
			}catch(\Exception $e) {
				$this->getLogger()->error("The selected data provider crashed. JSON will be used instead.");
				$this->dataProvider = new JSONDataProvider($this, $cacheSize);
			}
		$this->getLogger()->debug(TF::BOLD . "Loading Plot Clearing settings");
		if($this->getConfig()->get("FastClearing", false) and $this->getServer()->getPluginManager()->getPlugin("WorldStyler") === null) {
			$this->getConfig()->set("FastClearing", false);
			$this->getLogger()->info(TF::BOLD . "WorldStyler not found. Legacy clearing will be used.");
		}
		$this->getLogger()->debug(TF::BOLD . "Loading economy settings");
		// Initialize EconomyProvider
		if($this->getConfig()->get("UseEconomy", false) === true) {
			if(($plugin = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI")) !== null) {
				if($plugin instanceof EconomyAPI) {
					$this->economyProvider = new EconomySProvider($plugin);
					$this->getLogger()->debug("Eco set to EconomySProvider");
				}else
					$this->getLogger()->debug("Eco not instance of EconomyAPI");
			}
			if(!isset($this->economyProvider)) {
				$this->getLogger()->info("No supported economy plugin found!");
				$this->getConfig()->set("UseEconomy", false);
				//$this->getConfig()->save();
			}
		}
		$this->getLogger()->debug(TF::BOLD . "Loading MyPlot Commands");
		// Register command
		$this->getServer()->getCommandMap()->register("myplot", new Commands($this));
	}

	public function onEnable() : void {
		$this->getLogger()->debug(TF::BOLD . "Loading Events");

        $this->getServer()->getPluginManager()->registerEvents(new PlayerChatListener(), $this);
		$eventListener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($eventListener, $this);
		$this->getLogger()->debug(TF::BOLD . "Registering Loaded Levels");
		foreach($this->getServer()->getWorldManager()->getWorlds() as $level) {
			$eventListener->onLevelLoad(new WorldLoadEvent($level));
		}
		$this->getLogger()->debug(TF::BOLD.TF::GREEN."Enabled!");

		$this->saveResource("plotsquaredpm.yml");

		$plotsquared = new Config($this->getDataFolder() . "plotsquaredpm.yml");
        foreach ($plotsquared->get("borders", []) as $name => $data) {
            if (isset($data["id"]) and isset($data["perm"])) {
                $block = explode(':', $data["id"]);
                if (count($block) === 2 and is_numeric($block[0]) and is_numeric($block[1])) {
                    $block = BlockFactory::getInstance()->get((int) $block[0], (int) $block[1]);
                    self::$borders[] = new Border($name, $block, $data["perm"]);
                }
            }
        }

        foreach ($plotsquared->get("walls", []) as $name => $data) {
            if (isset($data["id"]) and isset($data["perm"])) {
                $block = explode(':', $data["id"]);
                if (count($block) === 2 and is_numeric($block[0]) and is_numeric($block[1])) {
                    $block = BlockFactory::getInstance()->get((int) $block[0], (int) $block[1]);
                    self::$walls[] = new Wall($name, $block, $data["perm"]);
                }
            }
        }

        self::$prefix = $plotsquared->get("prefix", "§l§aP2 §r");
	}

	public function addLevelSettings(string $levelName, PlotLevelSettings $settings) : bool {
		$this->levels[$levelName] = $settings;
		return true;
	}

	public function unloadLevelSettings(string $levelName) : bool {
		if(isset($this->levels[$levelName])) {
			unset($this->levels[$levelName]);
			$this->getLogger()->debug("Level " . $levelName . " settings unloaded!");
			return true;
		}
		return false;
	}

	public function onDisable() : void {
		if($this->dataProvider !== null)
			$this->dataProvider->close();
	}
}
