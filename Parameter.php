<?php

class Parameter
{
    // command name
    public const command = 'command';
    // namespace name
    public const namespace = 'namespace';
    // max tree levels
    public const levels = 'levels';
    // namespace/page identifiers to skip
    public const skippedIds = 'skippedIds';
    // Content definition page name
    public const contentDefinitionPageName = 'contentDefinitionPageName';

    // mode of operation
    public const mode = 'mode';

    // next namespace/page in selected namespace/page namespace
    public const next = 'Next';
    // previous namespace/page in selected namespace/page namespace
    public const previous = 'Previous';
    // first namespace/page in selected namespace/page namespace
    public const first = 'First';
    // last namespace/page in selected namespace/page namespace
    public const last = 'Last';
    // first namespace/page inside selected subnamespace
    public const inside = 'Inside';
    // namespace of selected namespace/page
    public const outside = 'Outside';
}