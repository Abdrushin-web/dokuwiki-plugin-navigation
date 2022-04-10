<?php

class LevelItem
{
    // next namespace/page in selected namespace/page namespace
    public const next = 'Next';
    // previous namespace/page in selected namespace/page namespace
    public const previous = 'Previous';
    // first namespace/page in selected namespace/page namespace
    public const first = 'First';
    // last namespace/page in selected namespace/page namespace
    public const last = 'Last';
    // first namespace/page inside selected subnamespace
    public const inside = 'Inside';
    // namespace of selected namespace/page
    public const outside = 'Outside';

    // page top
    public const top = 'Top';
    // page bottom
    public const bottom = 'Bottom';

    public static function isOnPage(string $levelItem) : bool
    {
        return $levelItem === LevelItem::top ||
               $levelItem === LevelItem::bottom;
    }

    public static function getTitle(IPlugin $plugin, array &$item) : string
    {
        $levelItem = $item[Navigation::levelItem];
        $levelItemName = $plugin->getLang($levelItem);
        $title = $item[Navigation::title];
        $title = $title ?
            $levelItemName.":\n".$title :
            $levelItemName;
        return $title;
    }

    public static function getDefaultList(string $mode) : array
    {
        if ($mode === LevelItemsMode::list)
            $levelItems[] = LevelItem::outside;
        $levelItems[] = LevelItem::first;
        $levelItems[] = LevelItem::previous;
        if ($mode === LevelItemsMode::symbols)
            $levelItems[] = LevelItem::outside;
        $levelItems[] = LevelItem::inside;
        $levelItems[] = LevelItem::next;
        $levelItems[] = LevelItem::last;
        return $levelItems;
    }
    public static function getDefaultPageMenuList() : array
    {
        $levelItems = LevelItem::getDefaultList(LevelItemsMode::symbols);
        array_insert_array($levelItems, 0, [ LevelItem::bottom ]);
        return $levelItems;
    }
}