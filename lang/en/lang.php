<?php
/**
 * English language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../LangId.php');

$lang[LangId::contentDefinitionPageTitle] = 'Content';

// British time
$lang[LangId::dateFormat] = 'd/m/Y';
$lang[LangId::timeFormat] = 'G:i';
$lang[LangId::dateTimeFormat] = $lang[LangId::dateFormat].' '.$lang[LangId::timeFormat];

$lang[LevelItem::next] = LevelItem::next;
$lang[LevelItem::previous] = LevelItem::previous;
$lang[LevelItem::first] = LevelItem::first;
$lang[LevelItem::last] = LevelItem::last;
$lang[LevelItem::inside] = LevelItem::inside;
$lang[LevelItem::outside] = LevelItem::outside;
$lang[LevelItem::top] = LevelItem::top;
$lang[LevelItem::bottom] = LevelItem::bottom;