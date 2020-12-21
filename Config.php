<?php

@include 'LevelItem.php';

class Config
{
    public const contentDefinitionPageName = 'contentDefinitionPageName';
    public const versionsDefinitionPageName = 'versionsDefinitionPageName';

    public const pageMenuLevelItems = 'pageMenuLevelItems';

    public static function getDefaultPageMenuLevelItems() : string
    {
        return implode(',', LevelItem::getDefaultPageMenuList());
    }
    public static function getPageMenuLevelItemLangKey(string $levelItem) : string
    {
        return Config::pageMenuLevelItems.'_o_'.$levelItem;
    }
    public static function translatePageMenuLevelItems(array $lang)
    {
        foreach (LevelItem::getDefaultPageMenuList() as $levelItem)
            $lang[Config::getPageMenuLevelItemLangKey($levelItem)] = $lang[$levelItem];
    }
    public static function hasPageMenuLevelItems(IPlugin $plugin) : bool
    {
        return $plugin->getConf(Config::pageMenuLevelItems);
    }
    public static function getPageMenuLevelItems(IPlugin $plugin) : array
    {
        $value = $plugin->getConf(Config::pageMenuLevelItems, null);
        return $value ?
            explode(',', $value) :
            [];
    }

    public const sneakyIndex = 'sneaky_index';

    public const useHeading = 'useheading';
    public const content = 'content';
    public const versions = 'versions';
    public const navigation = 'navigation';

    public const datadir = 'datadir';
}