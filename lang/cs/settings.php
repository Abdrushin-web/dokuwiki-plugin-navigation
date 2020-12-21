<?php
/**
 * Czech language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../Config.php');
@include('lang.php');

// keys need to match the config setting name

$lang[Config::contentDefinitionPageName] = 'Název stránky s definicí obsahu';
$lang[Config::versionsDefinitionPageName] = 'Název stránky s definicí verzí';

$lang[Config::pageMenuLevelItems] = 'Navigační menu stránky';
Config::translatePageMenuLevelItems($lang);
