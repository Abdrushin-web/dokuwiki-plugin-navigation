<?php
/**
 * English language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../Config.php');

// keys need to match the config setting name

$lang[Config::contentDefinitionPageName] = 'Content Definition Page Name';

$lang[Config::pageMenuLevelItems] = 'Page Navigation Menu';
Config::translatePageMenuLevelItems($lang);