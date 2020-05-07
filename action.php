<?php

if(!defined('DOKU_INC')) die();

require_once 'Content.php';
require_once 'Ids.php';
require_once 'IPlugin.php';
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
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'onPageEdit');
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'onPageSave');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'onUseCache');
        //$controller->register_hook('HTML_EDITFORM_OUTPUT', 'AFTER', $this, 'onEditForm');
        //$controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, '_loadindex');
        //$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, '_showsort');
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

    // /**
    //  * Render a defined page as index.
    //  *
    //  * @author Samuele Tognini <samuele@samuele.netsons.org>
    //  *
    //  * @param Doku_Event $event
    //  * @param mixed      $param not defined
    //  */
    // function _loadindex(&$event, $param) {
    //     if('index' != $event->data) return;
    //     if(!file_exists(wikiFN($this->getConf('page_index')))) return;
    //     global $lang;
    //     print '<h1><a id="index" name="index">'.$lang['btn_index']."</a></h1>\n";
    //     print p_wiki_xhtml($this->getConf('page_index'));
    //     $event->preventDefault();
    //     $event->stopPropagation();

    // }

    // /**
    //  * Display the indexmenu sort number.
    //  *
    //  * @author Samuele Tognini <samuele@samuele.netsons.org>
    //  *
    //  * @param Doku_Event $event
    //  * @param mixed      $param not defined
    //  */
    // function _showsort(&$event, $param) {
    //     global $ID, $ACT, $INFO;
    //     if($INFO['isadmin'] && $ACT == 'show') {
    //         if($n = p_get_metadata($ID, 'indexmenu_n')) {
    //             ptln('<div class="info">');
    //             ptln($this->getLang('showsort').$n);
    //             ptln('</div>');
    //         }
    //     }
    // }
}
