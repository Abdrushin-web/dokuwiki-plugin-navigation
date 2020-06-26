<?php

use dokuwiki\Parsing\ParserMode\Eol;

require_once 'ACL.php';
require_once 'array.php';
require_once 'Config.php';
require_once 'DateTimeMode.php';
require_once 'IPlugin.php';
require_once 'LangId.php';
require_once 'Paths.php';
require_once 'Text.php';

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
        $text = '';
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
            // page/namespace name with optional title and enclosing to internal wiki link
            $name = $content[$i];
            $name = trim($name);
            if (!$name)
                continue;
            $name = ltrim($name, '* '); // strip unordered list
            $name = trim($name, '[]'); // strip internal wiki link: [[name|title]]
            // check optional title
            $parts = explode('|', $name, 2);
            if (count($parts) == 2)
            {
                $name = $parts[0];
                $title = $parts[1];
            }
            else
                $title = null;
            // allow full id instead of name only, but skip it if it is outside of $namespace
            list(
                Navigation::namespace => $ns,
                Navigation::name => $name,
                Navigation::isNamespace => $isNamespace
                ) =
                Ids::getNamespaceAndName($name);
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
            Content::setTitle($id, $title, $isNamespace);
            $result[$id] =
            [
                Navigation::title => $title,
                Navigation::order => $i,
                Navigation::isNamespace => $isNamespace
            ];
        }
        return $result ?? [];
    }

    public static function setTitle(string $id, string $title, bool $isNamespace)
    {
        if ($isNamespace)
        {
            $namespacePageId = Ids::getNamespacePageId($id);
            if (!$namespacePageId)
                return;
            $id = $namespacePageId;
        }
        $noTitle = !$title;
        p_set_metadata(
            $id,
            $noTitle ?
                [] :
                [ Metadata::title => $title ],
            $noTitle, // render
            !$noTitle // persist
            );
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

    /**
     * Get navigation content tree
     * 
     * @param PluginInterface $plugin     Plugin requesting the tree
     * @param bool|null       $inPage     True for in page tree, false for navigation tree, null for content page prefilling
     * @param string          $namespace  Name of namespace to start at. '' means root one.
     * @param int             $levels     Number of tree child levels to get. 0 means all.
     * @param array           $skippedIds Page identifiers to skip
     */
    public static function getTree(
        IPlugin $plugin,
        $inPage,
        string $namespace,
        int $levels = 0,
        array $skippedIds = []
        ) : array
    {
        $items = Content::searchNamespace($namespace, 'Content::searchNamespaceItem', $levels, $skippedIds);
        $namespaceToDefinitionPageContent = array();
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
            if ($inPage !== null)
                Content::ensureTitle($item, $id, $inPage);
        }
        usort($items, 'Content::sortTree');
        if ($inPage === false)
            Content::addDefinitionPageItems($plugin, $namespace, $items);
        else
            Content::getAndRemoveDefinitionPageItems($plugin, $items);
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
                $order1 > $order2 ? 1 : -1;
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

    private static function ensureTitle(array &$item, string $id, bool $inPage)
    {
        $title = $item[Navigation::title];
        if (!isset($title))
        {
            $name = $item[Navigation::name];
            $isNamespace = $item[Navigation::isNamespace];
            if ($isNamespace)
            {
                $namespacePageId = $item[Navigation::namespacePageId];
                $title = Ids::getNamespaceTitle($name, $namespacePageId, $inPage);
            }
            else
                $title = Ids::getPageTitle($id, $name, $inPage);
            $item[Navigation::title] = $title;
        }
    }

    public static function searchNamespace(
        string $namespace,
        string $searchItemMethodName,
        int $levels = 0,
        array $skippedIds = []
        ) : array
    {
        global $conf;
        $items = array();
        $parameters =
        [
            Parameter::levels => $levels,
            Parameter::skippedIds => $skippedIds
        ];
        $folder = utf8_encodeFN(str_replace(NamespaceSeparator, PathSeparator, $namespace));
        search($items, $conf[Config::datadir], $searchItemMethodName, $parameters, $folder);
        return $items;
    }

    public static function searchNamespaceItem(array &$items, string $basePath, string $path, string $type, int $level, array $parameters) : bool
    {
        $levels = $parameters[Parameter::levels];
        $skippedIds = $parameters[Parameter::skippedIds];
        // check level
        if (
            $levels > 0 &&
            $level > $levels
            )
        {
            return false;
        }
        $id = pathID($path);
        list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($id);
        if (!$namespace)
            $id = NamespaceSeparator.$id;
        // namespace
        if ($type == 'd')
        {
            $id .= NamespaceSeparator;
            if (!ACL::canReadNamespace($id))
                return false;
            $isNamespace = true;
            $namespacePageId = Ids::getNamespacePageId($id);
        }
        // page
        else
        {
            if (
                !ACL::canReadPage($id) ||
                $id === Ids::getNamespacePageId(Ids::getNamespaceId($namespace))
                )
            {
                return false;
            }
        }
        if (in_array($id, $skippedIds, true))
            return false;
        // item
        $item = Ids::getNamespaceAndName($id);
        $item[Navigation::id] = $id;
        $item[Navigation::isNamespace] = $isNamespace;
        $item[Navigation::level] = $level;
        $item[Navigation::namespacePageId] = $namespacePageId;
        $items[] = $item;
        return true;
    }

    private static function getAndRemoveDefinitionPageItems(IPlugin $plugin, array &$items) : array
    {
        $pageName = Content::getDefinitionPageName($plugin);
        for ($i = count($items) - 1; $i >= 0; $i--)
        {
            $item = $items[$i];
            $isNamespace = $item[Navigation::isNamespace];
            if ($isNamespace)
                continue;
            $name = $item[Navigation::name];
            if ($name === $pageName)
            {
                $namespace = $item[Navigation::namespace];
                $namespaceIdToPageItem[Ids::getNamespaceId($namespace)] = $item;
                unset($items[$i]);
                $removed = true;
            }
        }
        if ($removed)
            $items = array_values($items); // reindex
        return $namespaceIdToPageItem ?? [];
    }

    private static function addDefinitionPageItems(IPlugin $plugin, string $namespace, array &$items)
    {
        $namespaceIdToPageItem = Content::getAndRemoveDefinitionPageItems($plugin, $items);
        $pageName = Content::getDefinitionPageName($plugin);
        for ($i = count($items) - 1; $i >= 0; $i--)
        {
            $item = $items[$i];
            $isNamespace = $item[Navigation::isNamespace];
            if (!$isNamespace)
                continue;
            $namespaceId = $item[Navigation::id];
            $level = $item[Navigation::level] + 1;
            Content::insertDefinitionPageItem($plugin, $items, $namespaceId, $pageName, $level, $namespaceIdToPageItem, $i + 1);
        }
        $namespaceId = Ids::getNamespaceId($namespace);
        Content::insertDefinitionPageItem($plugin, $items, $namespaceId, $pageName, 1, $namespaceIdToPageItem, 0);
    }

    private static function insertDefinitionPageItem(
        IPlugin $plugin,
        array &$items,
        string $namespaceId,
        string $pageName,
        int $level,
        array &$namespaceIdToPageItem,
        int $index
        )
    {
        $pageId = $namespaceId.$pageName;
        if (!ACL::canWrite($pageId))
            return;
        $item = $namespaceIdToPageItem[$namespaceId];
        if (!isset($item))
        {
            $namespace = Ids::getNamespaceName($namespaceId);
            $item = [
                Navigation::id => $pageId,
                Navigation::namespace => $namespace,
                Navigation::name => $pageName,
                Navigation::level => $level
            ];
        }
        $item[Navigation::title] = $plugin->getLang(LangId::contentDefinitionPageTitle);
        array_insert($items, $index, [ $item ]);
    }

    public static function getLastTreeChange(string $id) : array
    {
        list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($id);
        $items = Content::searchNamespace($namespace, 'Content::searchLastChange');
        return $items[0] ?? [];
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
        if ($lastChange &&
            $lastChange[Metadata::date] < $time)
        {
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
}