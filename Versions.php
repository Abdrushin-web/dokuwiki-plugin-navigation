<?php

require_once 'Config.php';
require_once 'Text.php';

class Versions
{
    public static function getDefinitionPageName(IPlugin $plugin) : string
    {
        return $plugin->getConf(Config::versionsDefinitionPageName, Config::versions);
    }

    public static function getDefinitionPageId(IPlugin $plugin, string $namespace, bool &$exists) : string
    {
        $id = Content::getDefinitionPageName($plugin);
        resolve_pageid($namespace, $id, $exists);
        return $id;
    }

    public static function processDefinitionPageContentTexts(string $oldContent, string $newContent)
    {
        $oldPageIds = [];
        Versions::processDefinitionPageContentText($oldContent, $oldPageIds, true);
        Versions::processDefinitionPageContentText($newContent, $oldPageIds, false);
    }

    private static function processDefinitionPageContentText(string $content, array &$oldPageIds, bool $fillOldPageIds)
    {
        $content = Text::toLines($content);
        $versions = [];
        $lastIndex = count($content) - 1;
        for ($i = 0; $i < count($content); $i++)
        {
            $line = $content[$i];
            $version = Ids::parseUnorderedListItemIdWithOptionalTitle($line);
            $id = $version[Navigation::id];
            if ($id)
            {
                $versions[] = $version;
                if (!$fillOldPageIds)
                    unset($oldPageIds[$id]);
            }
            // versions gathered from a list
            if ($versions &&
                (!$id ||
                 $i === $lastIndex))
            {
                $count = count($versions);
                foreach ($versions as $version)
                {
                    $id = $version[Navigation::id];
                    if ($fillOldPageIds)
                        $oldPageIds[$id] = true;
                    // clear versions for singles
                    else if ($count == 1)
                        Versions::setMetadata($id);
                    // set versions for multiples
                    else
                        Versions::setMetadata($id, $versions);
                }
                $versions = [];
            }
        }
        // remove versions for removed page ids
        if (!$fillOldPageIds)
        {
            foreach ($oldPageIds as $id => $_)
                Versions::setMetadata($id);
        }
    }

    const MetadataKey = 'versions';
    

    public static function setMetadata(string $id, array &$versions = [])
    {
        Metadata::set($id, Versions::MetadataKey, $versions);
    }

    public static function get(array &$parameters) : array
    {
        $root = true;
        $diff = true;
        foreach ($parameters as $parameter)
        {
            switch ($parameter)
            {
                case Parameter::noRoot:
                    $root = false;
                    break;
                case Parameter::noDiff:
                    $diff = false;
                    break;
                default:
                    $ids[] = Parameter::getIdFromOptionalLink($parameter);
                    break;
            }
        }
        // for id
        $id = $ids[0];
        if (!$id)
        {
            $id = Ids::currentPageId();
            $ids[0] = $id;
        }
        $versions = Metadata::get($id, Versions::MetadataKey);
        //print_r($versions);
        // filter versions by more than one id
        if (count($ids) > 1)
        {
            $versions =  array_filter(
                $versions,
                function ($version) use ($ids)
                {
                    return array_search($version[Navigation::id], $ids) !== false;
                });
            $versions = array_values($versions);
        }
        return
        [
            Navigation::id => $id,
            Navigation::versions => $versions,
            Navigation::root => $root,
            Navigation::diff => $diff
        ];
    }

    public static function getNamespaceIds(string $id1, string $id2 = '') : array
    {
        if (!$id2)
            $id2 = Ids::currentPageId();
        $namespace1 = getNS($id1);
        $namespace2 = getNS($id2);
        $commonNamespace = Ids::getCommonNamespace($namespace1, $namespace2);
        $namespaceIds = [];
        while ($namespace1 &&
                $namespace1 != $commonNamespace)
        {
            $namespaceIds[] = Ids::getNamespaceId($namespace1);
            $namespace1 = getNS($namespace1);
        }
        $namespaceIds = array_reverse($namespaceIds);
        return $namespaceIds;
    }

    public static function getTitle(string $id1, string $title, string $id2 = '', $inPage = false) : string
    {
        if (!$title)
        {
            $namespaceIds = Versions::getNamespaceIds($id1, $id2);
            foreach ($namespaceIds as $namespaceId)
            {
                $namespaceTitle = Ids::getNamespaceIdTitle($namespaceId, $inPage);
                $title = Versions::addBreadcrumb($title, $namespaceTitle);
            }
            if (!$namespaceIds ||
                !Ids::isNamespacePageId($id1))
            {
                $pageTitle = Ids::getPageTitle($id1, '', $inPage);
                $title = Versions::addBreadcrumb($title, $pageTitle);
            }
        }
        return $title;
    }

    public static function addBreadcrumb(string $text, string $part) : string
    {
        return $text ?
            ($text . ' » ' . $part) :
            $part;
    }

    public static function getDiffAnchorLinkId(string $diffAnchorPageId) : string
    {
        return "VersionDiffFor:$diffAnchorPageId";
    }

    public static function getDiffAnchorLink(string $diffPageId, string $diffAnchorPageId) : string
    {
        $anchorId = Versions::getDiffAnchorLinkId($diffAnchorPageId);
        $link = wl($diffPageId);
        $link = "$link#$anchorId";
        return $link;
    }

    public static function getDiffAnchorLinkData(array &$parameters) : array
    {
        $data[Parameter::diffPageId] = Parameter::getIdFromOptionalLink($parameters[0]);
        $data[Parameter::diffAnchorPageId] = Parameter::getIdFromOptionalLink($parameters[1]);
        $data[Parameter::title] = Parameter::textOrEmpty($parameters[2]);
        return $data;
    }

    public static function getDiffLinkData(array &$parameters) : array
    {
        $data[Parameter::page1] = Parameter::getIdWithOptionalTitleFromOptionalLink($parameters[0]);
        $data[Parameter::page2] = Parameter::getIdWithOptionalTitleFromOptionalLink($parameters[1]);
        $data[Parameter::title] = Parameter::textOrEmpty($parameters[2]);
        $data[Parameter::diffType] = Parameter::textOrEmpty($parameters[3]);
        return $data;
    }
}