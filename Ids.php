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

    public static function getNamespaceIdTitle(string $id, bool $inPage) : string
    {
        list(Navigation::name => $name) = Ids::getNamespaceAndName($id);
        $pageId = Ids::getNamespacePageId($id);
        return Ids::getNamespaceTitle($name, $pageId, $inPage);
    }

    public static function getPageTitle(string $id, string $name, bool $inPage) : string
    {
        if (Ids::useHeading($inPage))
            $value = p_get_first_heading($id);
        if (!isset($value))
            $value = $name;
        return $value;
    }

    public static function parseUnorderedListItemIdWithOptionalTitle(string $text) : array
    {
        // page/namespace id with optional title and enclosing to internal wiki link
        $id = ltrim($text);
        $level = (strlen($text) - strlen($id)) / 2; // list indentation level
        $id = rtrim($id);
        $title = '';
        if ($id)
        {
            // unordered list
            if ($id[0] == '*')
            {
                $id = ltrim($id, '* '); // strip unordered list
                $id = trim($id, '[]'); // strip internal wiki link: [[name|title]]
                // check optional title
                $parts = explode('|', $id, 2);
                if (count($parts) == 2)
                {
                    $id = $parts[0];
                    $title = $parts[1];
                }
            }
            else
                $id = '';
        }
        return
        [
            Navigation::id => $id,
            Navigation::title => $title,
            Navigation::level => $level
        ];
    }

    public static function setTitle(string $id, string $title, bool $isNamespace = false)
    {
        if ($isNamespace)
        {
            $namespacePageId = Ids::getNamespacePageId($id);
            if (!$namespacePageId)
                return;
            $id = $namespacePageId;
        }
        if (!$title)
            $title = null;
        p_set_metadata(
            $id,
            [ Metadata::title => $title ]);
        if (!$title)
        {
            // remove title from persistent metadata
            $metadata = p_read_metadata($id);
            $persistentMetadata = &$metadata[Metadata::persistent];
            unset($persistentMetadata[Metadata::title]);
            p_save_metadata($id, $metadata);
            // parse title to current metadata
            p_get_first_heading($id, METADATA_RENDER_UNLIMITED);
        }
    }

    public static function getCommonNamespace(string $namespace1, string $namespace2) : string
    {
        if ($namespace1 == $namespace2)
            $common = $namespace1;
        $common = '';
        do
        {
            $first1 = Ids::trimFirstNamespace($namespace1);
            $first2 = Ids::trimFirstNamespace($namespace2);
            if ($first1 &&
                $first1 == $first2)
            {
                $common = $common ?
                    Ids::join($common, $first1) :
                    $first1;
            }
            else
                break;
        }
        while (true);
        return $common;
    }

    public static function trimFirstNamespace(string &$namespace) : string
    {
        $index = strpos($namespace, NamespaceSeparator);
        if ($index === false)
        {
            $result = $namespace;
            $namespace = '';
        }
        else
        {
            $result = substr($namespace, 0, $index);
            $namespace = substr($namespace, $index + 1);
        }
        return $result;
    }
}