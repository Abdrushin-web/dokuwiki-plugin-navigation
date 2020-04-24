<?php

class Text
{
    public static function toLines(string $text) : array
    {
        return preg_split("/\r\n|\n|\r/", $text);
    }
}