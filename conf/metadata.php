<?php
/**
 * Options for the navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

$meta[Config::contentDefinitionPageName] = [ 'string' ];
$meta[Config::versionsDefinitionPageName] = [ 'string' ];
$meta[Config::pageMenuLevelItems] =
[
    'multicheckbox',
    '_other' => 'exists',
    '_choices' => LevelItem::getDefaultPageMenuList()
];