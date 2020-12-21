<?php
/**
 * Deutsch language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../Config.php');
@include('lang.php');

// keys need to match the config setting name

$lang[Config::contentDefinitionPageName] = 'Der Name der Inhaltsdefinitionsseite';
$lang[Config::versionsDefinitionPageName] = 'Der Name der Versionsdefinitionsseite';

$lang[Config::pageMenuLevelItems] = 'Seitennavigationsmenü';
Config::translatePageMenuLevelItems($lang);
