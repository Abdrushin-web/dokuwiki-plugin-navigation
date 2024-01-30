<?php

class DateTimeMode
{
    public const Date = 'Date';
    public const Time = 'Time';
    public const DateTime = 'DateTime';

    public static function isMode($text, $mode = null)
    {
        $text = trim($text);
        if ($mode)
            return strcasecmp($text, $mode) === 0;
        return
            strcasecmp($text, DateTimeMode::Date) === 0 ||
            strcasecmp($text, DateTimeMode::Time) === 0 ||
            strcasecmp($text, DateTimeMode::DateTime) === 0;
    }
}