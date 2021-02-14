<?php

class Html
{
    public static function TagOpen(string $name, string $cssClass = '') : string
    {
        return "<$name class=\"$cssClass\">";
    }
    public static function TagClose(string $name) : string
    {
        return "</$name>";
    }
    public static function Tag(string $name, string $content, string $cssClass = '') : string
    {
        return Html::TagOpen($name, $cssClass).$content.Html::TagClose($name);
    }
}