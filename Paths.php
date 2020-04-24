<?php

const PathSeparator = '/';

class Paths
{
    public static function join(string ... $parts) : string
    {
        return join(PathSeparator, $parts);
    }
}