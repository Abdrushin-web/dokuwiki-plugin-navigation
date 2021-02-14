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
require_once 'Command.php';
require_once 'Content.php';
require_once 'CSS.php';
require_once 'DateTimeMode.php';
require_once 'Html.php';
require_once 'Ids.php';
require_once 'IPlugin.php';
require_once 'LevelItem.php';
require_once 'Metadata.php';
require_once 'Navigation.php';
require_once 'Parameter.php';
require_once 'RenderMode.php';

class syntax_plugin_navigation
    extends DokuWiki_Syntax_Plugin
    implements IPlugin
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
        list (
            Parameter::command => $command,
            Parameter::parameters => $parameters
            ) = Parameter::processNavigationSyntax($match);
        switch ($command)
        {
            case Command::treeMenu:
            case Command::list:
            case Command::tree:
            case Command::contentList:
            case Command::contentTree:
                $data = Content::prepareTree($this, $command, $parameters);
                break;
            case Command::lastTreeChange:
                $data = Content::getLastTreeChangeFromParameters($this, $parameters);
                break;
            case Command::levelMenu:
                $data = Content::getLevelItems($this, $parameters);
                break;
            case Command::versions:
                $data = Versions::get($parameters);
                break;
            case Command::versionDiffLink:
                $data = Versions::getDiffLinkData($parameters);
                break;
            case Command::versionDiffAnchorLink:
                $data = Versions::getDiffAnchorLinkData($parameters);
                break;
            case Command::namespaceLink:
                global $ID;
                $namespace = $parameters[0];
                if (!$namespace)
                    list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($ID);
                $data[Navigation::id] = Ids::getNamespaceId($namespace);
                break;
            case Command::link:
                global $ID;
                $data[Navigation::id] = $parameters[0] ?? $ID;
                break;
        }
        $data[Parameter::command] = $command;
        return $data;
    }

    const MetadataKey = 'syntax';
    
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
        if ($mode === RenderMode::xhtml)
        {
            $command = $data[Parameter::command];
            unset($data[Parameter::command]);
            switch ($command)
            {
                case Command::treeMenu:
                case Command::list:
                case Command::tree:
                case Command::contentList:
                case Command::contentTree:
                    $this->renderTree($renderer, $data, $command !== Command::treeMenu);
                    break;
                case Command::namespaceLink:
                    $this->renderLink($renderer, $data[Navigation::id], true);
                    break;
                case Command::link:
                    $this->renderLink($renderer, $data[Navigation::id], false);
                    break;
                case Command::lastTreeChange:
                    $this->renderLastTreeChange($renderer, $data);
                    break;
                case Command::levelMenu:
                    $this->renderLevelItems($renderer, $data);
                    break;
                case Command::versions:
                    $this->renderVersions($renderer, $data);
                    break;
                case Command::versionDiffLink:
                    $this->renderVersionDiffLink($renderer, $data);
                    break;
                case Command::versionDiffAnchorLink:
                    $this->renderVersionDiffAnchorLink($renderer, $data);
                    break;
                default:
                    return false;
            }
            return true;
        }
        else if ($mode === RenderMode::metadata)
        {
            $renderer->meta[Metadata::getKey(syntax_plugin_navigation::MetadataKey)] = true;
            return true;
        }
        else
            return false;
    }

    function renderTree(Doku_Renderer_xhtml $renderer, array &$data, bool $inPage)
    {
        // in page
        if ($inPage)
        {
            $renderer->listu_open(CSS::navigationList);
            $renderer->doc .= html_buildlist(
                $data,
                CSS::navigationList,
                'syntax_plugin_navigation::htmlListItem'
                );
        }
        // menu
        else
        {
            $renderer->listu_open(CSS::navigationMenu);
            foreach ($data as &$item)
            {
                $definitionPageType = $item[Navigation::definitionPageType];
                if ($definitionPageType)
                    $item[Navigation::title] = $this->getLang(LangId::definitionPageTitle($definitionPageType));
            }
            $renderer->doc .= html_buildlist(
                $data,
                CSS::navigationMenu,
                'syntax_plugin_navigation::htmlMenuItem',
                'syntax_plugin_navigation::htmlMenuLi'
                );
        }
        $renderer->listu_close();
    }

    function renderLink(Doku_Renderer $renderer, string $id, bool $namespace)
    {
        $link  = $this->wikiLink($id, $namespace);
        $renderer->externallink($link);
    }

    public static function htmlMenuLi(array $item) : string
    {
        if ($item[Navigation::isNamespace])
        {
            $open = $item[Navigation::isNamespaceOpen];
            $class = $open ? 'open' : 'closed';
        }
        else
            $class = 'level'.$item[Navigation::level];
        $levelItem = $item[Navigation::levelItem];
        if ($levelItem)
            $class .= ' '.$levelItem;
        return '<li class="'.$class.'">';
    }

    public static function htmlMenuItem(array $item) : string
    {
        $levelItem = $item[Navigation::levelItem];
        if ($levelItem)
        {
            if (LevelItem::isOnPage($levelItem))
            {
                $item[Navigation::title] = $item[Navigation::levelItemName];
            }
            else
            {
                $levelItemName = $item[Navigation::levelItemName];
                $result = $levelItemName.': ';
            }
        }
        $result .= syntax_plugin_navigation::htmlMenuListItem($item, true);
        return $result;
    }

    public static function htmlListItem(array $item) : string
    {
        return syntax_plugin_navigation::htmlMenuListItem($item, false);
    }

    public static function htmlMenuListItem(array &$item, bool $showCurrent) : string
    {
        // global $ID;
        $id = $item[Navigation::id];
        $title = $item[Navigation::title];
        $result = html_wikilink($id, $title);
        // if (
        //     $showCurrent &&
        //     $ID === $id
        //     )
        // {
        //     $result = Html::Tag('strong', CSS::navigationCurrentItem, $result);
        // }
        return $result;
    }

    public function wikiLink($id, $namespace = false)
    {
        if ($namespace)
            $id = getNS($id);
        $link = wl($id, '', true);
        if ($namespace)
            $link .= '/';
        return $link;
    }

    public function renderLastTreeChange(Doku_Renderer $renderer, array &$data)
    {
        $time = $data[Metadata::date];
        $mode = $data[Parameter::mode];
        $renderer->doc .= Content::FormatTime($this, $time, $mode);
    }

    public function renderLevelItems(Doku_Renderer $renderer, array &$data)
    {
        $mode = $data[0][Parameter::mode];
        switch ($mode)
        {
            case LevelItemsMode::list:
                foreach ($data as &$item)
                    $item[Navigation::levelItemName] = $this->getLang($item[Navigation::levelItem]);
                $this->renderTree($renderer, $data, false);
                break;
            case LevelItemsMode::symbols:
                $renderer->doc .= $this->getLevelItemSymbols($data);
                break;
        }
    }

    public function getLevelItemSymbols(array &$items) : string
    {
        $result = '<div class="'.Css::levelItems."\">\n";
        foreach ($items as $item)
            $result .= $this->getLevelItemSymbol($item)."\n";
        $result .= '</div>';
        return $result;
    }

    public function getLevelItemSymbol(array &$item) : string
    {
        $levelItem = $item[Navigation::levelItem];
        $title = LevelItem::getTitle($this, $item);
        $id = $item[Navigation::id];
        $class = Css::levelItem.' '.$levelItem;
        if ($id)
        {
            $uri = wl($id);
            if (!$item[Navigation::readable])
                $class .= ' '.Css::disabled;
        }
        else
            $class .= ' '.Css::disabled;
        return $uri ?
            "<a class=\"$class\" title=\"$title\" href=\"$uri\">&nbsp;</a>" :
            "<span class=\"$class\" title=\"$title\">&nbsp;</span>";
    }

    public function renderVersions(Doku_Renderer $renderer, array &$data)
    {
        $versions = $data[Navigation::versions];
        if (!$versions)
            return;
        $forId = $data[Navigation::id];
        $currentId = Ids::currentPageId();
        $inPage = $forId !== $currentId;
        $root = $data[Navigation::root];
        $diff = $data[Navigation::diff];
        $difftype = '';
        foreach ($versions as $version)
        {
            if ($version[Navigation::id] === $forId)
            {
                $forVersion = $version;
                break;
            }
        }
        if ($diff)
        {
            $linkId = Versions::getDiffAnchorLinkId($forId);
            $renderer->doc .= "<ul id=\"$linkId\">";
        }
        else
            $renderer->listu_open();
        $level = 1;
        if ($root)
        {
            $renderer->listitem_open($level++, true);
            $renderer->doc .= '<div class="li">'.$this->getLang(LangId::definitionPageTitle(Config::versions)).'</div>';
            $renderer->listu_open();
        }
        if ($diff)
            $forTitle = $forVersion[Navigation::title] ?? '';
        $previousVersionLevel = 1;
        $versionIndex = 0;
        $beforeForId = true;
        foreach ($versions as $version)
        {
            $id = $version[Navigation::id];
            $isForId = $id === $forId;
            if ($isForId)
                $beforeForId = false;
            $select = $isForId && $diff;
            $title = $version[Navigation::title];
            $contentTitle = Versions::getTitle(
                $id, $title,
                $inPage ?
                    $currentId :
                    $forId,
                $inPage);
            $versionLevel = $version[Navigation::level];
            for ($i = 0; $i < $previousVersionLevel - $versionLevel; $i++)
            {
                $renderer->listu_close();
                $level--;
            }
            if ($versionLevel < $previousVersionLevel &&
                $versionLevel == 1)
            {
                $renderer->listitem_close();
            }
            for ($i = 0; $i < $versionLevel - $previousVersionLevel; $i++)
            {
                $renderer->listu_open();
                $level++;
            }
            $renderer->listitem_open($level);
            if ($select)
                $renderer->doc .= '<strong>';
            // if ($id === $currentId)
            //     $renderer->doc .= '<div class="li">'.$contentTitle.'</div>';
            // else
            $renderer->internallink($id, $contentTitle);
            if ($select)
                $renderer->doc .= '</strong>';
            if ($diff && !$isForId)
                $renderer->doc .= '&nbsp;'.$this->versionDiffLink($forId, $forTitle, $id, $title, $inPage, $difftype, '', $beforeForId);
            $versionIndex++;
            $nextVersionLevel = $versions[$versionIndex][Navigation::level];
            if ($versionLevel == $nextVersionLevel)
                $renderer->listitem_close();
            $previousVersionLevel = $versionLevel;
        }
        for ($i = 1; $i < $previousVersionLevel; $i++)
            $renderer->listu_close();
        if ($previousVersionLevel > 1)
            $renderer->listitem_close();
        if ($root)
        {
            $renderer->listu_close();
            $renderer->listitem_close();
        }
        $renderer->listu_close();
    }

    public function versionDiffImage(string $title = '') : string
    {
        if (!$title)
        {
            global $lang;
            $title = $lang['diff'];
        }
        return '<img src="'.DOKU_BASE.'lib/images/diff.png" style="min-width:15px" width="15" title="'.$title.'" alt="'.$title.'" />';
    }

    public function versionDiffLink(
        string $id1, string $title1,
        string $id2, string $title2,
        bool $inPage,
        string $difftype = '',
        string $title = '',
        bool $swap = false
        ) : string
    {
        $name1 = Versions::getTitle($id1, $title1, $id2, $inPage);
        $name2 = Versions::getTitle($id2, $title2, $id1, $inPage);
        $content = $this->versionDiffLinkContent($title);
        return html_diff_another_page_navigationlink($difftype, $name1, $id2, $name2, $content, $swap, $id1);
    }

    public function versionDiffLinkContent(string $title = '') : string
    {
        $image = $this->versionDiffImage();
        $content = $title ?
            "$title&nbsp;$image" :
            $image;
        return $content;
    }

    public function renderVersionDiffLink(Doku_Renderer $renderer, array &$data)
    {
        $page1 = $data[Parameter::page1];
        $page2 = $data[Parameter::page2];
        $title = $data[Parameter::title];
        $difftype = $data[Parameter::diffType];
        $renderer->doc .= $this->versionDiffLink(
            $page1[Navigation::id], $page1[Navigation::title],
            $page2[Navigation::id], $page2[Navigation::title],
            true, $difftype, $title);
    }

    public function renderVersionDiffAnchorLink(Doku_Renderer $renderer, array &$data)
    {
        $diffPageId = $data[Parameter::diffPageId];
        $diffAnchorPageId = $data[Parameter::diffAnchorPageId];
        $title = $data[Parameter::title];
        $link = Versions::getDiffAnchorLink($diffPageId, $diffAnchorPageId);
        $content = $this->versionDiffLinkContent($title);
        $renderer->doc .= "<a href=\"$link\">$content</a>";
    }
}