<?php

if(!defined('DOKU_INC')) die();

require_once 'Content.php';
require_once 'Ids.php';
require_once 'IPlugin.php';
require_once 'LevelMenuItem.php';
require_once 'Navigation.php';
require_once 'Metadata.php';
require_once 'Parameter.php';

class action_plugin_navigation
    extends DokuWiki_Action_Plugin
    implements IPlugin
{

    /**
     * plugin should use this method to register its handlers with the dokuwiki's event controller
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object.
     */
    function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'onPageEdit');
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'onPageSave');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'onUseCache');
        global $conf;
        if (Config::hasPageMenuLevelItems($this))
            $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addPageMenuLevelItems', null, 1);
    }
    
    function onPageEdit(&$event)
    {
        global $ACT;
        if ($ACT !== 'edit')
            return;
        global $ID;
        list(Navigation::namespace => $namespace, Navigation::name => $name) = Ids::getNamespaceAndName($ID);
        if ($name !== Content::getDefinitionPageName($this))
            return;
        // Content page
        $form = $event->data;
        $wikiText = &$form->_content[0];
        $content = $wikiText['_text'];
        $content = Content::parseDefinitionPageContentText($namespace, $content);
        Content::setDefinitionPageContent($this, $namespace, $content);
        $content = Content::getTree($this, null, $namespace, 1);
        $content = Content::formatDefinitionPageContentText($content);
        $wikiText['_text'] = $content;
    }

    function onPageSave(&$event)
    {
        $name = $event->data[2];
        $revision = $event->data[3];
        if (
            !$event->result || // saving failed
            $name !== Content::getDefinitionPageName($this) ||
            $revision // saved revision
            )
        {
            return;
        }
        // saved current
        $namespace = $event->data[1];
        if ($namespace === false)
            $namespace = '';
        $content = Content::parseDefinitionPageContent($this, $namespace);
        Content::setDefinitionPageContent($this, $namespace, $content);
    }

    function onUseCache(&$event)
    {
        global $ID;
        $navigation = p_get_metadata($ID, Metadata::navigation);
        if ($navigation)
            $event->result = false;
    }

    function addPageMenuLevelItems(&$event)
    {
        global $INFO;
        if ($event->data['view'] !== 'page' || // not page menu
            !$INFO['exists']) // page does not exist
        {
            return;
        }
        $levelItems = Config::getPageMenuLevelItems($this);
        $items = Content::getLevelItems($this, $levelItems);
        $menuItems = &$event->data['items'];
        foreach ($items as $item)
            $menuItems[] = new LevelMenuItem($this, $item);
    }
}
