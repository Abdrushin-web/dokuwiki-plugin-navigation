<?php

require_once 'Actions.php';
require_once 'Navigation.php';

use dokuwiki\Menu\Item\AbstractItem;

class LevelMenuItem
    extends AbstractItem
{
    public function __construct(IPlugin $plugin, array &$item)
    {
        $this->type = Actions::show;
        parent::__construct();
        $this->id = $item[Navigation::id];
        $this->plugin = $plugin;
        $this->item = $item;
    }

    public function getLevelItem() : string
    {
        return $this->item[Navigation::levelItem];
    }

    public function getLabel() : string
    {
        return LevelItem::getTitle($this->plugin, $this->item);
    }

    public function getSvg() : string
    {
        return __DIR__.'/images/'.$this->getLevelItem().'.svg';
    }

    private $plugin;
    private $item;
}