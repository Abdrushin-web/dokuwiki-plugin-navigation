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
                    else
                        Versions::setMetadata(
                            $id,
                            $count == 1 ?
                                // clear versions for singles
                                [] :
                                // set versions for multiples
                                $versions);
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

    public static function setMetadata(string $id, array $versions = [])
    {
        $metadata =
        [
            Metadata::navigation =>
            [
                Metadata::versions => $versions
            ]
        ];
        p_set_metadata($id, $metadata);
    }

    public static function get(array $parameters) : array
    {
        $id = $parameters[0] ?? Ids::currentPageId();
        $versions = p_get_metadata($id, Metadata::navigation.' '.Metadata::versions);
        return
        [
            Navigation::id => $id,
            Navigation::versions => $versions
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

    public static function getTitle(string $id1, $title, string $id2 = '', $inPage = false) : string
    {
        if (!$title)
        {
            $namespaceIds = Versions::getNamespaceIds($id1, $id2);
            foreach ($namespaceIds as $namespaceId)
            {
                $namespaceTitle = Ids::getNamespaceIdTitle($namespaceId, $inPage);
                $title = Versions::addBreadcrumb($title, $namespaceTitle);
            }
            $pageTitle = Ids::getPageTitle($id1, '', $inPage);
            $title = Versions::addBreadcrumb($title, $pageTitle);
        }
        return $title;
    }

    public static function addBreadcrumb(string $text, string $part) : string
    {
        return $text ?
            ($text . ' » ' . $part) :
            $part;
    }
}