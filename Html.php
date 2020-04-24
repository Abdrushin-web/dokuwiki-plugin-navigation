<?php

class Html
{
    public static function Tag(string $name, string $cssClass, string $content) : string
    {
        return "<$name class=\"$cssClass\">$content</$name>";
    }
}