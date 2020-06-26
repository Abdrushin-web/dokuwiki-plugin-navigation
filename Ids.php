<?php

const NamespaceSeparator = ':';
const CurrentNamespaceName = '.';

class Ids
{
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
        if (strpos($id, NamespaceSeparator) === 0)
            $id = substr($id, strlen(NamespaceSeparator));
        return $id;
    }

    public static function getNamespaceName(string $id) : string
    {
        $name = substr($id, 0, -1);
        $name = Ids::trimLeadingNamespaceSeparator($name);
        return $name;
    }
    public static function getNamespaceAndName(string $id) : array
    {
        $isNamespace = Ids::isNamespace($id);
        if ($isNamespace)
            $id = substr($id, 0, -1);
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

    public static function getNamespaceId(string $namespace, string $name = '') : string
    {
        if ($name === '')
            $id = $namespace.NamespaceSeparator;
        else
        {
            $id = Ids::join($namespace, $name);
            if ($id !== NamespaceSeparator)
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
     * @param string  $id Namespace id
     * @return string Id of namespace page if found, otherwise ''
     */
    public static function getNamespacePageId(string $id) : string
    {
        $namespace = rtrim($id, NamespaceSeparator);
        $exists = null;
        resolve_pageid($namespace, $id, $exists);
        return $exists ?
            !$namespace ?
                NamespaceSeparator.$id :
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
                $useHeading === '1' ||
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