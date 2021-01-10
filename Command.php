<?php

class Command
{
    // navigation menu tree with root namespace as default
    public const treeMenu = 'TreeMenu';
    // navigation level menu for current namespace
    public const levelMenu = 'LevelMenu';

    // navigation in page list with current namespace as default
    public const list = 'List';
    // navigation in page tree with current namespace as default
    public const tree = 'Tree';
    // navigation in page list with current namespace as default and without current page
    public const contentList = 'ContentList';
    // navigation in page tree with current namespace as default and without current page
    public const contentTree = 'ContentTree';
    
    // current namespace/page URI
    public const link = 'Link';
    public const namespaceLink = 'NamespaceLink';
    
    // last namespace tree change
    public const lastTreeChange = 'LastTreeChange';

    // page versions
    public const versions = 'Versions';
    // page versions diff link
    public const versionDiffLink = 'VersionDiffLink';
    // page versions diff anchor link
    public const versionDiffAnchorLink = 'VersionDiffAnchorLink';
}