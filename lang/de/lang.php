<?php
/**
 * Deutsch language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../LangId.php');
@include('../../Config.php');

$lang[LangId::definitionPageTitle(Config::content)] = 'Inhalt';
$lang[LangId::definitionPageTitle(Config::versions)] = 'Versionen';

$lang[LangId::dateFormat] = 'j.n.Y';
$lang[LangId::timeFormat] = 'G:i';
$lang[LangId::dateTimeFormat] = $lang[LangId::dateFormat].' '.$lang[LangId::timeFormat];

$lang[LevelItem::next] = 'Folgend';
$lang[LevelItem::previous] = 'Vorstehend';
$lang[LevelItem::first] = 'Erste';
$lang[LevelItem::last] = 'Letzte';
$lang[LevelItem::inside] = 'Innerhalb';
$lang[LevelItem::outside] = 'Draußen';
$lang[LevelItem::top] = 'Hinauf';
$lang[LevelItem::bottom] = 'Nieder';