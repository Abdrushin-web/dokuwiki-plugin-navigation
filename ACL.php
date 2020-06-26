<?php

class ACL
{
    public static function isHidden(string $id) : bool
    {
        $id = Ids::trimLeadingNamespaceSeparator($id);
        return isHiddenPage($id);
    }

    public static function canRead(string $id) : bool
    {
        return auth_quickaclcheck($id) >= AUTH_READ;
    }

    public static function canReadPage(string $id) : bool
    {
        return
            !ACL::isHidden($id) &&
            ACL::canRead($id);
    }
    
    public static function canReadNamespace(string $id) : bool
    {
        global $conf;
        return
            !ACL::isHidden($id) &&
            (
                !$conf[Config::sneakyIndex] ||
                ACL::canRead($id)
            );
    }

    public static function canWrite(string $id) : bool
    {
        return auth_quickaclcheck($id) >= AUTH_EDIT;
    }
}