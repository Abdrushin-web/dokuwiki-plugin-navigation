<?php
/**
 * DokuWiki Plugin navigation (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Marek Ištvánek <marek.istvanek@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

require_once 'Html.php';
require_once 'IPlugin.php';

class admin_plugin_navigation
    extends DokuWiki_Admin_Plugin
    implements IPlugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 5;
    }

    public function getMenuText($language)
    {
        return 'Navigation: Process data ...';
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        ptln(Html::Tag('h1', 'Processing'));
        ptln(Html::Tag('h2', 'Content definitions'));
        $this->processDefinitions(
            Content::getDefinitionPageName($this),
            function(array &$item)
            {
                $namespace = $item[Navigation::namespace];
                $content = Content::parseDefinitionPageContent($this, $namespace);
                Content::setDefinitionPageContent($this, $namespace, $content);
            });
        ptln(Html::Tag('h2', 'Versions definitions'));
        $this->processDefinitions(
            Versions::getDefinitionPageName($this),
            function(array &$item)
            {
                $content = rawWiki($item[Navigation::id]);
                Versions::processDefinitionPageContentTexts('', $content);
            });
        ptln(Html::Tag('h1', 'Done'));
    }

    function processDefinitions($pageName, callable $process)
    {
        ptln(Html::TagOpen('ul'));
        $search = function(array &$result, string $basePath, string $path, string $type, int $level, array $parameters)
            use ($pageName, $process)
            {
                if ($type === 'd')
                    return true;
                $id = pathID($path);
                $item = Ids::getNamespaceAndName($id);
                $item[Navigation::id] = $id;
                if ($item[Navigation::name] !== $pageName)
                    return;
                ptln(Html::Tag('li', $id));
                call_user_func_array($process, [&$item]);
            };
        Content::searchNamespace($this, '', $search);
        ptln(Html::TagClose('ul'));
    }
}

