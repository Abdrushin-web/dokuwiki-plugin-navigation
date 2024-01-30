<?php

class Parameter
{
    // command name
    public const command = 'command';
    // command parameters
    public const parameters = 'parameters';
    // namespace name
    public const namespace = 'namespace';
    // namespace/page identifiers to skip
    public const skippedIds = 'skippedIds';
    // definition page names
    public const definitionPageNames = 'definitionPageNames';

    // mode of operation
    public const mode = 'mode';

    // versions
    public const title = 'title';
    public const noDiff = 'NoDiff';
    public const noRoot = 'NoRoot';
    public const noCurrent = 'NoCurrent';
    // version diff
    public const page1 = 'page1';
    public const page2 = 'page2';
    public const diffType = 'diffType';
    // version diff anchor
    public const diffPageId = 'diffPageId';
    public const diffAnchorPageId = 'diffAnchorPageId';

    public static function processNavigationSyntax(string $text) : array
    {
        // {Navigation|Command[|Parameter1|...|ParameterN]}
        $text = trim($text, '{}');
        $items = explode('|', $text);
        $command = $items[1] ?? '';
        $parameters = [];
        for ($i = 2; $i < count($items); $i++)
        {
            $previousParameter = $i > 2 ?
                $items[$i] :
                '';
            $parameter = $items[$i];
            $hasLinkStart = Parameter::hasLinkStart($parameter);
            $hasLinkEnd = Parameter::hasLinkEnd($parameter);
            // allow links [[id|title]] which are now split into 2 parameters which must be joined again into 1
            if ($hasLinkStart &&
                !$hasLinkEnd)
            {
                continue;
            }
            else if (!$hasLinkStart &&
                     $hasLinkEnd &&
                     Parameter::hasLinkStart($previousParameter))
            {
                $parameter = "$previousParameter|$parameter";
            }
            $parameters[] = $parameter;
        }
        $result[Parameter::command] = $command;
        $result[Parameter::parameters] = $parameters;
        return $result;
    }

    public static function hasLinkStart(string $text) : bool
    {
        return strpos($text, '[[') !== false;
    }

    public static function hasLinkEnd(string $text) : bool
    {
        return strpos($text, ']]') !== false;
    }

    public static function getIdWithOptionalTitleFromOptionalLink($parameter) : array
    {
        $result = Ids::parseIdWithOptionalTitleFromOptionalLink($parameter ?? '');
        if ($result[Navigation::id] === '.')
            $result[Navigation::id] = Ids::currentPageId();
        return $result;
    }

    public static function getIdFromOptionalLink($parameter) : string
    {
        list(Navigation::id => $id) = Parameter::getIdWithOptionalTitleFromOptionalLink($parameter);
        return $id;
    }

    public static function textOrEmpty($parameter) : string
    {
        return $parameter ?? '';
    }
}