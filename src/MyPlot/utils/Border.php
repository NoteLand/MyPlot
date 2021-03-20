<?php


namespace MyPlot\utils;

use pocketmine\block\Block;

class Border
{
    /** @var string $name */
    public $name;

    /** @var Block $block */
    public $block;

    public function __construct(string $name, Block $block)
    {
        $this->name = $name;
        $this->block = $block;
    }

    /** @return string */
    public function getName(): string
    {
        return $this->name;
    }

    /** @return Block */
    public function getBlock(): Block
    {
        return $this->block;
    }
}