<?php
/**
 * DokuWiki Plugin navigation (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Marek Ištvánek <marek.istvanek@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

require_once DOKU_INC.'inc/common.php';
require_once DOKU_INC.'inc/pageutils.php';
require_once DOKU_INC.'inc/search.php';
require_once 'ACL.php';
require_once 'Command.php';
require_once 'Config.php';
require_once 'CSS.php';
require_once 'Html.php';
require_once 'Ids.php';
require_once 'Navigation.php';
require_once 'Parameter.php';
require_once 'Paths.php';
require_once 'RenderMode.php';
require_once 'Text.php';

class syntax_plugin_navigation extends DokuWiki_Syntax_Plugin
{
    /**
     * @return string Syntax mode type
     */
    public function getType() : string
    {
        return 'substition';
    }

    // /**
    //  * @return string Paragraph type
    //  */
    // public function getPType() : string
    // {
    //     return 'block';
    // }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() : int
    {
        return 10;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{Navigation\|.+?(?:}|\|.*?})', $mode, 'plugin_navigation');
    }

    /**
     * Handle matches of the navigation syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // {Navigation|Command[|Parameter1|...|ParameterN]}
        $match = trim($match, '{}');
        $items = explode('|', $match);
        $command = $items[1];
        $parameters = array_slice($items, 2);
        switch ($command)
        {
            case Command::menu:
            case Command::list:
                $inPage = $command === Command::list;
                $namespace = $parameters[0] ?? '';
                $levels = intval($parameters[1]);
                $data = $this->getTree($inPage, $namespace, $levels);
                break;
        }
        $data[Parameter::command] = $command;
        return $data;
    }

    public function getNavigationPageId(string $namespace) : string
    {
        $id = $this->getConf(Config::navigationPageName, Config::navigation);
        $pageExists = null;
        resolve_pageid($namespace, $id, $pageExists);
        return $pageExists ? $id : '';
    }

    public function getNavigationPageData(string $namespace) : array
    {
        $id = $this->getNavigationPageId($namespace);
        // navigation page exists
        if ($id)
        {
            $content = rawWiki($id);
            $content = Text::toLines($content);
            for ($i = 0; $i < count($content); $i++)
            {
                // page/namespace name with optional title and enclosing to internal wiki link
                $name = $content[$i];
                $name = trim($name);
                if (!$name)
                    continue;
                $name = ltrim($name, '*- '); // strip optional (un)ordered list
                $name = trim($name, '[]'); // strip optional internal wiki link or unordered list
                $parts = explode('|', $name, 2);
                if (count($parts) == 2)
                {
                    $name = $parts[0];
                    $title = $parts[1];
                }
                else
                    $title = null;
                // allow full id instead of name when internal wiki link is used, but skip it if it is outside of $namespace
                list(Navigation::namespace => $ns, Navigation::name => $name) = Ids::getNamespaceName($name);
                if ($ns && $ns !== $namespace)
                    continue;
                // full id
                $isNamespace = Ids::namespaceExists($namespace, $name, $id);
                if (
                    !$isNamespace &&
                    !Ids::pageExists($namespace, $name, $id)
                    )
                {
                    continue;
                }
                $result[$id] =
                [
                    Navigation::title => $title,
                    Navigation::order => $i,
                    Navigation::isNamespace => $isNamespace
                ];
            }
        }
        return $result ?? [];
    }

    public function getTree(bool $inPage, string $namespace, int $levels = 0) : array
    {
        $items = syntax_plugin_navigation::searchNamespace($namespace, $levels);
        $namespaceToNavigationPageData = array();
        for ($i = count($items) - 1; $i >= 0; $i--)
        {
            $item = $items[$i];
            $id = $item[Navigation::id];
            if ($this->applyNavigationPageData($item, $id, $namespaceToNavigationPageData))
                $items[$i] = $item;
            syntax_plugin_navigation::ensureTitle($item, $id, $inPage);
        }
        usort($items, 'syntax_plugin_navigation::sortTree');
        return $items;
    }

    public static function sortTree(array $item1, array $item2) : int
    {
        $namespace1 = $item1[Navigation::namespace];
        $namespace2 = $item2[Navigation::namespace];
        // sort only inside same namespace
        if ($namespace1 !== $namespace2)
        {
            $result = 0;
        }
        // same namespace
        else
        {
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
        }
        return $result;
    }

    private function applyNavigationPageData(array &$item, string $id, array &$namespaceToNavigationPageData) : bool
    {
        $namespace = $item[Navigation::namespace];
        $navigationPageData = $namespaceToNavigationPageData[$namespace];
        if (!isset($navigationPageData))
            $namespaceToNavigationPageData[$namespace] = $navigationPageData = $this->getNavigationPageData($namespace);
        if (count($navigationPageData))
        {
            $navigationPageDataItem = $navigationPageData[$id];
            if (isset($navigationPageDataItem))
            {
                $item = array_merge($navigationPageDataItem, $item);
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

    public static function searchNamespace(string $namespace, int $levels) : array
    {
        global $conf;
        $items = array();
        $parameters =
        [
            Parameter::levels => $levels
        ];
        $folder = utf8_encodeFN(str_replace(NamespaceSeparator, PathSeparator, $namespace));
        search($items, $conf[Config::datadir], 'syntax_plugin_navigation::searchNamespaceItem', $parameters, $folder);
        return $items;
    }

    public static function searchNamespaceItem(array &$items, string $basePath, string $path, string $type, int $level, array $parameters) : bool
    {
        $levels = $parameters[Parameter::levels];
        $id = pathID($path);
        // namespace
        if ($type == 'd')
        {
            $id .= NamespaceSeparator;
            if (
                !ACL::canReadNamespace($id) ||
                // check level
                $levels > 0 &&
                $level >= $levels
                )
            {
                 return false;
            }
            $isNamespace = true;
            $namespacePageId = Ids::namespacePageId($id);
        }
        // page
        else if (!ACL::canReadPage($id))
            return false;
        // item
        $item = Ids::getNamespaceName($id);
        $item[Navigation::id] = $id;
        $item[Navigation::isNamespace] = $isNamespace;
        $item[Navigation::level] = $level;
        $item[Navigation::namespacePageId] = $namespacePageId;
        $items[] = $item;
        return true;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== RenderMode::xhtml)
            return false;
        $command = $data[Parameter::command];
        unset($data[Parameter::command]);
        switch ($command)
        {
            case Command::menu:
                $renderer->doc .= html_buildlist($data, CSS::navigationMenu, 'syntax_plugin_navigation::htmlMenuItem');
                break;
            case Command::list:
                $renderer->doc .= html_buildlist($data, CSS::navigationList, 'syntax_plugin_navigation::htmlListItem');
                break;
        }
        return true;
    }

    public static function htmlMenuItem(array $item) : string
    {
        return syntax_plugin_navigation::htmlMenuListItem($item, true);
    }

    public static function htmlListItem(array $item) : string
    {
        return syntax_plugin_navigation::htmlMenuListItem($item, false);
    }

    public static function htmlMenuListItem(array $item, bool $showCurrent) : string
    {
        global $ID;
        $id = $item[Navigation::id];
        $title = $item[Navigation::title];
        $result = html_wikilink($id, $title);
        if (
            $showCurrent &&
            $ID === $id
            )
        {
            $result = Html::Tag('strong', CSS::navigationCurrentItem, $result);
        }
        return $result;
    }
}