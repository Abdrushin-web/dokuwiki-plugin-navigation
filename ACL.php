
<?php

class ACL
{
    public static function canRead(string $id) : bool
    {
        return auth_quickaclcheck($id) >= AUTH_READ;
    }

    public static function canReadPage(string $id) : bool
    {
        return
            !isHiddenPage($id) &&
            ACL::canRead($id);
    }
    
    public static function canReadNamespace(string $id) : bool
    {
        global $conf;
        return
            !$conf[Config::sneakyIndex] ||
            ACL::canRead($id);
    }
}