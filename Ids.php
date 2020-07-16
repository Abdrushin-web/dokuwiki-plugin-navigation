<?php

const NamespaceSeparator = ':';
const CurrentNamespaceName = '.';

class Ids
{
    public static function currentPageId() : string
    {
        global $INFO;
        global $ID;
        $id = $INFO[Navigation::id] ?? $ID;
        list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($id);
        if (!$namespace)
            $id = NamespaceSeparator.$id;
        return $id;
    }

    public static function join(string ... $parts) : string
    {
        return join(NamespaceSeparator, $parts);
    }

    public static function isNamespace(string $id) : bool
    {
        return strrpos($id, NamespaceSeparator) === strlen($id) - strlen(NamespaceSeparator);
    }

    public static function trimLeadingNamespaceSeparator(string $id) : string
    {
        return ltrim($id, NamespaceSeparator);
    }
    public static function trimTrailingNamespaceSeparator(string $id) : string
    {
        return rtrim($id, NamespaceSeparator);
    }
    public static function trimNamespaceSeparator(string $id) : string
    {
        return trim($id, NamespaceSeparator);
    }

    public static function getNamespaceName(string $id) : string
    {
        return Ids::trimNamespaceSeparator($id);
    }
    public static function getNamespaceAndName(string $id) : array
    {
        $isNamespace = Ids::isNamespace($id);
        if ($isNamespace)
            $id = Ids::trimTrailingNamespaceSeparator($id);
        $namespace = getNS($id);
        if ($namespace === false)
            $namespace = '';
        $name = noNS($id);
        return [
            Navigation::namespace => $namespace,
            Navigation::name => $name,
            Navigation::isNamespace => $isNamespace
        ];
    }

    public static function isRootNamespace(string $id)
    {
        return $id === NamespaceSeparator;
    }

    public static function getNamespaceId(string $namespace, string $name = '') : string
    {
        if ($name === '')
            $id = $namespace.NamespaceSeparator;
        else
        {
            $id = $namespace ?
                Ids::join($namespace, $name) :
                $name;
            if (!Ids::isRootNamespace($id))
                $id .= NamespaceSeparator;
        }
        return $id;
    }

    public static function namespaceExists(string $namespace, string $name, string &$id) : bool
    {
        $id = Ids::getNamespaceId($namespace, $name);
        $idForFN = $id === NamespaceSeparator ?
            $id :
            substr($id, 0, -1);
        $folderPath = Paths::join(dirname(wikiFN($idForFN)), $name);
        return is_dir($folderPath);
    }

    /**
     * @param string  $id               Namespace id
     * @param bool    $emptyIfNotFound  Whether to return '' id if page is not found, otherwise page id
     * @return string Id of namespace page if found, otherwise '' if $emptyIfNotFound is true, otherwise page id
     */
    public static function getNamespacePageId(string $id, bool $emptyIfNotFound = true) : string
    {
        $namespace = Ids::trimTrailingNamespaceSeparator($id);
        $exists = null;
        resolve_pageid($namespace, $id, $exists);
        return
            $exists ||
            !$emptyIfNotFound ?
                (!$namespace ?
                    NamespaceSeparator.$id :
                    $id) :
                '';
    }

    public static function pageExists(string $namespace, string $name, string &$id) : bool
    {
        $id = Ids::join($namespace, $name);
        return page_exists($id);
    }

    public static function useHeading(bool $inPage) : bool
    {
        global $conf;
        $useHeading = $conf[Config::useHeading];
        return
            $useHeading !== 0 &&
            (
                $useHeading === '1' ||
                $inPage &&
                $useHeading === Config::content ||
                !$inPage &&
                $useHeading === Config::navigation
            );
    }

    public static function getNamespaceTitle(string $name, string $pageId, bool $inPage) : string
    {
        if (isset($pageId))
            $title = Ids::getPageTitle($pageId, $name, $inPage);
        if (!$title)
            $title = $name;
        return $title;
    }

    public static function getPageTitle(string $id, string $name, bool $inPage) : string
    {
        if (Ids::useHeading($inPage))
            $value = p_get_first_heading($id);
        if (!isset($value))
            $value = $name;
        return $value;
    }
}