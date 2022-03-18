<?php

class Metadata
{
    const persistent = 'persistent';
    const navigation = 'navigation';
    const title = 'title';
    const date = 'date';
    const modified = 'modified';
    const dateModified = Metadata::date.' '.Metadata::modified;

    public static function getKey(string $name)
    {
        return Metadata::navigation.'_'.$name;
    }

    public static function get(string $id, string $name)
    {
        $key = Metadata::getKey($name);
        $metadata = p_get_metadata($id, $key);
        return $metadata;
    }
    public static function set(string $id, string $name, $data) : bool
    {
        $key = Metadata::getKey($name);
        $metadata[$key] = $data;
        return p_set_metadata($id, $metadata);
    }

    public static function removeTitleFromPersistentMetadata(string $id)
    {
        $metadata = p_read_metadata($id);
        $persistentMetadata = &$metadata[Metadata::persistent];
        unset($persistentMetadata[Metadata::title]);
        p_save_metadata($id, $metadata);
    }
    public static function setTitle(string $id, $title)
    {
        if (!$title)
            $title = null;
        p_set_metadata(
            $id,
            [ Metadata::title => $title ]);
        if (!$title)
            Metadata::refreshTitle($id);
    }
    public static function refreshTitle(string $id)
    {
        Metadata::removeTitleFromPersistentMetadata($id);
        // parse title to current metadata
        p_get_first_heading($id, METADATA_RENDER_UNLIMITED);
    }
}