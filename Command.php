<?php

class Command
{
    // navigation menu tree with root namespace as default
    public const menu = 'Menu';
    // navigation in page list with current namespace as default
    public const list = 'List';
    // navigation in page tree with current namespace as default
    public const tree = 'Tree';
    // navigation in page tree with current namespace as default and without current page
    public const content = 'Content';
    
    // current namespace/page URI
    public const link = 'Link';
}