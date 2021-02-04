<?php

require_once 'ACL.php';
require_once 'array.php';
require_once 'Config.php';
require_once 'DateTimeMode.php';
require_once 'IPlugin.php';
require_once 'LangId.php';
require_once 'LevelItem.php';
require_once 'LevelItemsMode.php';
require_once 'Paths.php';
require_once 'Parameter.php';
require_once 'Text.php';
require_once 'Versions.php';

class Content
{
    public static function getDefinitionPageName(IPlugin $plugin) : string
    {
        return $plugin->getConf(Config::contentDefinitionPageName, Config::content);
    }

    public static function getDefinitionPageId(IPlugin $plugin, string $namespace, bool &$exists) : string
    {
        $id = Content::getDefinitionPageName($plugin);
        resolve_pageid($namespace, $id, $exists);
        return $id;
    }

    public static function formatDefinitionPageContentText(array $content) : string
    {
        $text = DOKU_LF;
        foreach ($content as $item)
        {
            $id = $item[Navigation::id];
            $title = $item[Navigation::title];
            $text .= "  * [[$id|$title]]".DOKU_LF;
        }
        return $text;
    }

    public static function parseDefinitionPageContentText(string $namespace, string $content) : array
    {
        $content = Text::toLines($content);
        for ($i = 0; $i < count($content); $i++)
        {
            $line = $content[$i];
            list(Navigation::id => $id, Navigation::title => $title) = Ids::parseUnorderedListItemIdWithOptionalTitle($line);
            if (!$id)
                continue;
            // allow full id instead of name only, but skip it if it is outside of $namespace
            list(
                Navigation::namespace => $ns,
                Navigation::name => $name,
                Navigation::isNamespace => $isNamespace
                ) =
                Ids::getNamespaceAndName($id);
            if (
                $ns &&
                $ns !== CurrentNamespaceName &&
                $ns !== $namespace
                )
            {
                continue;
            }
            // full id, but skip it if it does not exist
            $id = '';
            if (
                $isNamespace &&
                !Ids::namespaceExists($namespace, $name, $id) ||
                !$isNamespace &&
                !Ids::pageExists($namespace, $name, $id)
                )
            {
                continue;
            }
            Ids::setTitle($id, $title, $isNamespace);
            $result[$id] =
            [
                Navigation::title => $title,
                Navigation::order => $i,
                Navigation::isNamespace => $isNamespace
            ];
        }
        return $result ?? [];
    }

    public static function parseDefinitionPageContent(IPlugin $plugin, string $namespace) : array
    {
        $exists = false;
        $id = Content::getDefinitionPageId($plugin, $namespace, $exists);
        if ($exists)
        {
            $content = rawWiki($id);
            $result = Content::parseDefinitionPageContentText($namespace, $content);
        }
        else
            $result = [];
        return $result;
    }

    const MetadataKey = 'content';

    public static function getDefinitionPageContent(IPlugin $plugin, string $namespace) : array
    {
        $exists = false;
        $id = Content::getDefinitionPageId($plugin, $namespace, $exists);
        if ($exists)
        {
            $content = p_get_metadata($id, Content::MetadataKey);
            // definition might be uploaded without online editation so lets parse it and save for later usage
            if (!isset($content))
            {
                $content = Content::parseDefinitionPageContent($plugin, $namespace);
                Content::setDefinitionPageContent($plugin, $namespace, $content);
            }
        }
        else
            $content = [];
        return $content;
    }

    public static function setDefinitionPageContent(IPlugin $plugin, string $namespace, array $content) : bool
    {
        $exists = false;
        $id = Content::getDefinitionPageId($plugin, $namespace, $exists);
        if ($exists)
        {
            $data = [ Content::MetadataKey => $content ];
            $done = p_set_metadata($id, $data);
        }
        else
            $done = false;
        return $done;
    }

    public static function prepareTree(IPlugin $plugin, string $command, array $parameters) : array
    {
        $inPage = $command !== Command::treeMenu;
        $namespace = $parameters[0] ??
            ($inPage ?
                '.' : // current
                '');   // root
        if ($namespace === '.')
        {
            global $ID;
            list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($ID);
        }
        else
            list(Navigation::namespace => $namespace) = Ids::parseNamespaceFromOptionalLink($namespace);
        $levelsText = $parameters[1];
        $levels =
            $command === Command::list ||
            $command === Command::contentList ?
                1 :
                ($levelsText ?
                    intval($levelsText) :
                    0);
        $skippedIds = [];
        if ($command === Command::contentList ||
            $command === Command::contentTree)
        {
            global $ID;
            $skippedIds[] = $ID;
        }
        foreach ($parameters as $parameter)
        {
            if ($parameter[0] !== '-')
                continue;
            $id = substr($parameter, 1);
            if ($id === '.')
            {
                global $ID;
                $id = $ID;
            }
            else
            list(Navigation::id => $id) = Ids::parseIdWithOptionalTitleFromOptionalLink($id);
            $skippedIds[] = $id;
        }
        return Content::getTree($plugin, $inPage, $namespace, $levels, $skippedIds);
    }

    /**
     * Get navigation content tree
     * 
     * @param PluginInterface $plugin                     Plugin requesting the tree
     * @param bool|null       $inPage                     True for in page tree, false for navigation tree, null for content page prefilling
     * @param string          $namespace                  Name of namespace to start at. '' means root one.
     * @param int             $levels                     Number of tree child levels to get. 0 means all.
     * @param array           $skippedIds                 Page identifiers to skip
     * @param bool            $addDefinitionPages         Whether to add Content/Versions definition pages
     */
    public static function getTree(
        IPlugin $plugin,
        $inPage,
        string $namespace = '',
        int $levels = 0,
        array $skippedIds = [],
        bool $addDefinitionPages = true
        ) : array
    {
        $tree = Content::getWholeTree($plugin);
        $items = [];
        $namespaceLevel = 0;
        if ($inPage !== false)
            $addDefinitionPages = false;
        $menu = $inPage === false;
        if ($menu)
        {
            $currentPageId = Ids::currentPageId();
            //list(Navigation::namespace => $currentPageNamespaceId) = Ids::getNamespaceAndName($currentPageId);
            //$currentPageNamespaceId = Ids::getNamespaceId($currentPageNamespaceId);
            $isNamespaceOpen = true;
        }
        foreach ($tree as $item)
        {
            $itemNamespace = $item[Navigation::namespace];
            $level = $item[Navigation::level];
            $namespaceStart = !$namespaceLevel &&
                              $itemNamespace === $namespace;
            if ($namespaceStart)
                $namespaceLevel = $level;
            if (!$namespaceLevel)
                continue;
            $namespaceEnd = $namespaceLevel &&
                            (
                                // outside namespace level?
                                $level < $namespaceLevel ||
                                // on namespace level, but in different namespace?
                                $level === $namespaceLevel &&
                                $itemNamespace !== $namespace
                            );
            if ($namespaceEnd)
                break;
            $id = $item[Navigation::id];
            $skipDefinitionPage = $item[Navigation::definitionPageType] &&
                                  (
                                      !$addDefinitionPages ||
                                      !ACL::canWrite($id)
                                  );
            if ($skipDefinitionPage)
                continue;
            $level -= $namespaceLevel - 1;
            $skipLevel = $levels > 0 &&
                         $level > $levels;
            if ($skipLevel)
                continue;
            $isNamespace = $item[Navigation::isNamespace];
            $readable = $isNamespace &&
                        ACL::canReadNamespace($id) ||
                        !$isNamespace &&
                        ACL::canReadPage($id);
            if (!$readable && $menu)
                continue;
            $item[Navigation::readable] = $readable;
            $idWithoutNamespace = substr($id, strlen($namespace) + 1);
            $skipId = in_array($id, $skippedIds, true) ||
                      in_array($idWithoutNamespace, $skippedIds, true);
            if ($skipId)
                continue;
            if ($menu)
            {
                if ($isNamespace)
                {
                    $isNamespaceOpen = strpos($currentPageId, $id) === 0;
                    $item[Navigation::isNamespaceOpen] = $isNamespaceOpen;
                    $parentNamespaceId = Ids::getNamespaceId($itemNamespace);
                    $isParentNamespaceOpen = strpos($currentPageId, $parentNamespaceId) === 0;
                    if (!$isParentNamespaceOpen)
                        continue;
                }
                else if (!$isNamespaceOpen)
                    continue;
            }
            $item[Navigation::level] = $level;
            Content::setTitle($item, $inPage);
            $items[] = $item;
        }
        return $items;
    }

    private static function setTitle(array &$item, $inPage)
    {
        if ($inPage === null)
            unset($item[Navigation::title]);
        else if (!Ids::useHeading($inPage))
        {
            $name = $item[Navigation::name];
            $item[Navigation::title] = $name;
        }
    }

    static function getRootDefinitionPageId(IPlugin $plugin) : string
    {
        return NamespaceSeparator.Content::getDefinitionPageName($plugin);
    }

    const TreeMetadataKey = 'contentTree';
    static $tree;

    static function getWholeTree(IPlugin $plugin) : array
    {
        if (!Content::$tree)
        {
            Content::$tree = $_SESSION[Content::TreeMetadataKey];
            if (!Content::$tree)
            {
                $rootDefinitionPageId = Content::getRootDefinitionPageId($plugin);
                Content::$tree = p_get_metadata($rootDefinitionPageId, Content::TreeMetadataKey);
                if (!Content::$tree)
                    Content::cacheWholeTree($plugin);
                $_SESSION[Content::TreeMetadataKey] = Content::$tree;
            }
        }
        return Content::$tree;
    }

    public static function cacheWholeTree(IPlugin $plugin)
    {
        Content::$tree = Content::doGetWholeTree($plugin);
        $rootDefinitionPageId = Content::getRootDefinitionPageId($plugin);
        $data = [ Content::TreeMetadataKey => Content::$tree ];
        p_set_metadata($rootDefinitionPageId, $data);
    }

    static function doGetWholeTree(IPlugin $plugin) : array
    {
        $items = Content::searchNamespace($plugin, '', 'Content::searchNamespaceItem');
        $namespaceToDefinitionPageContent = [];
        $namespaceToItem = [];
        for ($i = 0; $i < count($items); $i++)
        {
            $item = &$items[$i];
            $id = $item[Navigation::id];
            $itemNamespace = $item[Navigation::namespace];
            $item[Navigation::namespaceItem] = &$namespaceToItem[$itemNamespace];
            $isNamespace = $item[Navigation::isNamespace];
            if ($isNamespace)
                $namespaceToItem[Ids::getNamespaceName($id)] = &$item;
            Content::applyDefinitionPageContent($plugin, $item, $id, $namespaceToDefinitionPageContent);
            Content::ensureTitle($item, $id);
        }
        usort($items, 'Content::sortTree');
        Content::addDefinitionPageItems($plugin, '', $items);
        return $items;
    }

    public static function sortTree(array $item1, array $item2) : int
    {
        $namespace1 = $item1[Navigation::namespace];
        $namespace2 = $item2[Navigation::namespace];
        // find common namespace
        while ($namespace1 !== $namespace2)
        {
            $level1 = $item1[Navigation::level];
            $level2 = $item2[Navigation::level];
            // go to parent
            if ($level1 === $level2)
            {
                $item1 = $item1[Navigation::namespaceItem];
                $item2 = $item2[Navigation::namespaceItem];
                $namespace1 = $item1[Navigation::namespace];
                $namespace2 = $item2[Navigation::namespace];
            }
            // go to same level
            else
            {
                if ($level1 > $level2)
                {
                    while ($level1 > $level2)
                    {
                        $item1 = $item1[Navigation::namespaceItem];
                        $level1--;
                        $namespace1 = $item1[Navigation::namespace];
                    }
                    // item1 inside item2
                    if ($item1 === $item2)
                        return 1;
                }
                if ($level2 > $level1)
                {
                    while ($level2 > $level1)
                    {
                        $item2 = $item2[Navigation::namespaceItem];
                        $level2--;
                        $namespace2 = $item2[Navigation::namespace];
                    }
                    // item2 inside item1
                    if ($item1 === $item2)
                        return -1;
                }
            }
        }
        // same namespace
        $order1 = $item1[Navigation::order];
        $order2 = $item2[Navigation::order];
        $set1 = isset($order1);
        $set2 = isset($order2);
        // one orderred first
        if ($set1 && !$set2)
            $result = -1;
        else if (!$set1 && $set2)
            $result = 1;
        // none orderred, use name
        else if (!$set1 && !$set2)
        {
            $name1 = $item1[Navigation::name];
            $name2 = $item2[Navigation::name];
            $result = strcoll($name1, $name2);
        }
        // both orderred
        else
        {
            $result = $order1 === $order2 ?
                0 :
                ($order1 > $order2 ? 1 : -1);
        }
        return $result;
    }

    private static function applyDefinitionPageContent(IPlugin $plugin, array &$item, string $id, array &$namespaceToContent) : bool
    {
        $namespace = $item[Navigation::namespace];
        $content = $namespaceToContent[$namespace];
        if (!isset($content))
            $namespaceToContent[$namespace] = $content = Content::getDefinitionPageContent($plugin, $namespace);
        if (count($content))
        {
            $contentItem = $content[$id];
            if (isset($contentItem))
            {
                $item = array_merge($contentItem, $item);
                return true;
            }
        }
        return false;
    }

    private static function ensureTitle(array &$item, string $id)
    {
        $title = $item[Navigation::title];
        if (!$title)
        {
            $name = $item[Navigation::name];
            $isNamespace = $item[Navigation::isNamespace];
            if ($isNamespace)
            {
                $namespacePageId = $item[Navigation::namespacePageId];
                $title = Ids::getNamespaceTitleOrName($name, $namespacePageId);
            }
            else
                $title = Ids::getPageTitleOrName($id, $name);
            $item[Navigation::title] = $title;
        }
    }

    public static function searchNamespace(IPlugin $plugin, string $namespace, string $searchItemMethodName) : array
    {
        $items = array();
        $folder = utf8_encodeFN(str_replace(NamespaceSeparator, PathSeparator, $namespace));
        global $conf;
        $parameters =
        [
            Parameter::definitionPageNames =>
                [
                    Content::getDefinitionPageName($plugin),
                    Versions::getDefinitionPageName($plugin),
                ]
        ];
        search($items, $conf[Config::datadir], $searchItemMethodName, $parameters, $folder);
        return $items;
    }

    public static function searchNamespaceItem(array &$items, string $basePath, string $path, string $type, int $level, array $parameters) : bool
    {
        $id = pathID($path);
        list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($id);
        // namespace
        if ($type == 'd')
        {
            $id .= NamespaceSeparator;
            $isNamespace = true;
            $namespacePageId = Ids::getNamespacePageId($id);
        }
        // page
        else
        {
            if (!$namespace)
                $id = NamespaceSeparator.$id;
            if ($id === Ids::getNamespacePageId(Ids::getNamespaceId($namespace)))
                return false;
            $definitionPageNames = $parameters[Parameter::definitionPageNames];
        }
        // item
        $item = Ids::getNamespaceAndName($id);
        // skip definition pages
        if (!$isNamespace &&
            array_search($item[Navigation::name], $definitionPageNames, true) !== false)
        {
            return false;
        }
        $item[Navigation::id] = $id;
        $item[Navigation::isNamespace] = $isNamespace;
        $item[Navigation::level] = $level;
        $item[Navigation::namespacePageId] = $namespacePageId;
        $items[] = $item;
        return true;
    }

    private static function addDefinitionPageItems(IPlugin $plugin, string $namespace, array &$items)
    {
        $pageTypeToName =
        [
            Config::content => Content::getDefinitionPageName($plugin),
            Config::versions => Versions::getDefinitionPageName($plugin)
        ];
        for ($i = count($items) - 1; $i >= 0; $i--)
        {
            $item = $items[$i];
            $isNamespace = $item[Navigation::isNamespace];
            if (!$isNamespace)
                continue;
            $namespaceId = $item[Navigation::id];
            $level = $item[Navigation::level] + 1;
            Content::insertDefinitionPageItems($items, $namespaceId, $pageTypeToName, $level, $i + 1);
        }
        $namespaceId = Ids::getNamespaceId($namespace);
        Content::insertDefinitionPageItems($items, $namespaceId, $pageTypeToName, 1, 0);
    }

    private static function insertDefinitionPageItems(
        array &$items,
        string $namespaceId,
        array &$pageTypeToName,
        int $level,
        int $index
        )
    {
        foreach ($pageTypeToName as $pageType => $pageName)
            Content::insertDefinitionPageItem($items, $namespaceId, $pageType, $pageName, $level, $index++);
    }

    private static function insertDefinitionPageItem(
        array &$items,
        string $namespaceId,
        string $pageType,
        string $pageName,
        int $level,
        int $index
        )
    {
        $pageId = $namespaceId.$pageName;
        $namespace = Ids::getNamespaceName($namespaceId);
        $item = [
            Navigation::id => $pageId,
            Navigation::namespace => $namespace,
            Navigation::name => $pageName,
            Navigation::level => $level
        ];
        $item[Navigation::definitionPageType] = $pageType;
        array_insert($items, $index, [ $item ]);
    }

    public static function getLastTreeChangeFromParameters(IPlugin $plugin, array $parameters) : array
    {
        global $ID;
        $modeIndex = count($parameters) > 1 ? 1 : 0;
        $id = $modeIndex ?
            Ids::getNamespaceId($parameters[0]) :
            $ID;
        $mode = $parameters[$modeIndex] ?? DateTimeMode::DateTime;
        $data = Content::getLastTreeChange($plugin, $id);
        $data[Parameter::mode] = $mode;
        return $data;
    }

    public static function getLastTreeChange(IPlugin $plugin, string $id) : array
    {
        list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($id);
        $items = Content::searchNamespace($plugin, $namespace, 'Content::searchLastChange');
        return $items[0] ?? [ Metadata::date => time() ];
    }

    public static function searchLastChange(array &$items, string $basePath, string $path, string $type, int $level, array $parameters) : bool
    {
        if ($type == 'd')
            return true;
        $id = pathID($path);
        // page
        $item[Metadata::date] = $time = p_get_metadata($id, Metadata::date.' '.Metadata::modified);
        if (!$time)
            return false;
        $lastChange = &$items[0];
        if ($lastChange)
        {
            if ($lastChange[Metadata::date] < $time)
                $lastChange = $item;
        }
        else
            $lastChange = $item;
        return true;
    }

    public static function FormatTime(IPlugin $plugin, int $time, string $mode = DateTimeMode::DateTime) : string
    {
        $format = $mode === DateTimeMode::Date ?
            $plugin->getLang(LangId::dateFormat) :
            ($mode === DateTimeMode::Time ?
                $plugin->getLang(LangId::timeFormat) :
                $plugin->getLang(LangId::dateTimeFormat));
        return date($format, $time);
    }

    public static function getLevelItems(IPlugin $plugin, array &$levelItems, string $id = '') : array
    {
        if (!$id)
            $id = Ids::currentPageId();
        $idInfo = Ids::getNamespaceAndName($id);
        $namespace = $idInfo[Navigation::namespace];
        $namespaceId = Ids::getNamespaceId($namespace);
        $namespacePageId = Ids::getNamespacePageId($namespaceId, false);
        if ($id === $namespacePageId)
            return Content::getLevelItems($plugin, $levelItems, $namespaceId);
        $mode = Content::getLevelItemsMode($levelItems);
        if (!$levelItems)
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
        }
        $items = [];
        foreach ($levelItems as $levelItem)
        {
            $item = Content::getLevelItem($plugin, $id, $idInfo, $levelItem, $items);
            if ($mode === LevelItemsMode::list &&
                !$item[Navigation::id])
            {
                continue;
            }
            $item[Parameter::mode] = $mode;
            $result[] = $item;
        }
        if ($result &&
            $mode === LevelItemsMode::list)
        {
            Content::clearLevelItemDuplicate($result, LevelItem::first, LevelItem::previous);
            Content::clearLevelItemDuplicate($result, LevelItem::last, LevelItem::next);
        }
        return $result ?? [];
    }

    public static function getLevelItemsMode(array &$levelItems)
    {
        $mode = $levelItems[0];
        if ($mode === LevelItemsMode::symbols ||
            $mode === LevelItemsMode::list)
        {
            $levelItems = array_slice($levelItems, 1);
        }
        else
            $mode = LevelItemsMode::list;
        return $mode;
    }

    public static function getLevelItem(IPlugin $plugin, string $id, array &$idInfo, string $levelItem, array &$items) : array
    {
        $namespace = $idInfo[Navigation::namespace];
        switch ($levelItem)
        {
            case LevelItem::next:
                $index = Content::indexOfNamespaceItem($plugin, $namespace, $items, $id);
                $item = $index >= 0 ?
                    $items[$index + 1] :
                    null;
                break;
            case LevelItem::previous:
                $index = Content::indexOfNamespaceItem($plugin, $namespace, $items, $id);
                $item = $index >= 0 ?
                    $items[$index - 1] :
                    null;
                break;
            case LevelItem::first:
                $index = Content::indexOfNamespaceItem($plugin, $namespace, $items, $id);
                $item = $index <= 0 ?
                    null :
                    $items[0];
                break;
            case LevelItem::last:
                $index = Content::indexOfNamespaceItem($plugin, $namespace, $items, $id);
                $lastIndex = count($items) - 1;
                $item =
                    $index < 0 ||
                    $index === $lastIndex ?
                        null :
                        $items[$lastIndex];
                break;
            case LevelItem::inside:
                if ($idInfo[Navigation::isNamespace])
                {
                    $subItems = [];
                    Content::ensureNamespaceItems($plugin, Ids::getNamespaceName($id), $subItems);
                    $item = $subItems[0];
                }
                else
                    $item = null;
                break;
            case LevelItem::outside:
                $namespaceId = Ids::getNamespaceId($namespace);
                if ($id === $namespaceId)
                    $item = null;
                else
                {
                    $item = Ids::getNamespaceAndName($namespaceId);
                    $item[Navigation::id] = $namespaceId;
                    $item[Navigation::title] = p_get_metadata(Ids::getNamespacePageId($namespaceId), Metadata::title) ??
                        $item[Navigation::name];
                }
                break;
            case LevelItem::top:
                $item[Navigation::id] = '#dokuwiki__header';
                break;
            case LevelItem::bottom:
                $item[Navigation::id] = '#dokuwiki__footer';
                break;
            default:
                $unknown = true;
        }
        $item[Navigation::levelItem] = $levelItem;
        $item[Navigation::level] = 1;
        return $item;
    }

    public static function getLevelItemIndex(array &$data, string $levelItem)
    {
        return array_search($levelItem, array_column($data, Navigation::levelItem));
    }

    public static function clearLevelItemDuplicate(array &$data, string $levelItem, string $levelItemDuplicate)
    {
        $index = Content::getLevelItemIndex($data, $levelItem);
        if ($index === false)
            return;
        $duplicateIndex = Content::getLevelItemIndex($data, $levelItemDuplicate);
        if ($duplicateIndex === false)
            return;
        if ($data[$index][Navigation::id] !== $data[$duplicateIndex][Navigation::id])
            return;
        unset($data[$duplicateIndex]);
        $data = array_values($data);
    }

    public static function indexOfNamespaceItem(IPlugin $plugin, string $namespace, array &$items, $id) : int
    {
        Content::ensureNamespaceItems($plugin, $namespace, $items);
        $index = array_search($id, array_column($items, Navigation::id));
        return $index === false ?
            -1 :
            $index;
    }

    public static function ensureNamespaceItems(IPlugin $plugin, string $namespace, array &$items)
    {
        if (!$items)
            $items = Content::getTree($plugin, false, $namespace, 1, [], false);
    }
}