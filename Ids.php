<?php

const NamespaceSeparator = ':';

class Ids
{
    public static function join(string ... $parts) : string
    {
        return join(NamespaceSeparator, $parts);
    }

    public static function isNamespace(string $id)
    {
        return substr($id, -1, 1) === NamespaceSeparator;
    }
    public static function getNamespaceName(string $id) : array
    {
        if (Ids::isNamespace($id))
            $id = substr($id, 0, strlen($id) - 1);
        $namespace = getNS($id);
        if ($namespace === false)
            $namespace = '';
        $name = noNS($id);
        return [
            Navigation::namespace => $namespace,
            Navigation::name => $name
        ];
    }

    public static function namespaceExists(string $namespace, string $name, string &$id) : bool
    {
        $id = Ids::join($namespace, $name);
        $folderPath = Paths::join(dirname(wikiFN($id)), $name);
        $id .= NamespaceSeparator;
        return is_dir($folderPath);
    }

    /**
     * @param $id Namespace id
     * @return string Id of namespace page if found, otherwise ''
     */
    public static function namespacePageId(string $id) : string
    {
        $namespace = rtrim(NamespaceSeparator);
        $id = NamespaceSeparator;
        $exists = null;
        resolve_pageid($namespace, $id, $exists);
        return $exists ?
            $id :
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
                $useHeading === 1 ||
                $inPage &&
                $useHeading === Config::content ||
                !$inPage &&
                $useHeading === Config::navigation
            );
    }

    public static function getNamespaceTitle(string $name, string $pageId, bool $inPage) : string
    {
        return isset($pageId) ?
            Ids::getPageTitle($pageId, $name, $inPage) :
            $name;
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