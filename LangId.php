<?php

class LangId
{
    public static function definitionPageTitle(string $pageType)
    {
        return $pageType.'DefinitionPageTitle';
    }

    const dateFormat = 'dateFormat';
    const timeFormat = 'timeFormat';
    const dateTimeFormat = 'dateTimeFormat';
}

@require_once('LevelItem.php');
