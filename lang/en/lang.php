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

$lang[Parameter::next] = Parameter::next;
$lang[Parameter::previous] = Parameter::previous;
$lang[Parameter::first] = Parameter::first;
$lang[Parameter::last] = Parameter::last;
$lang[Parameter::inside] = Parameter::inside;
$lang[Parameter::outside] = Parameter::outside;