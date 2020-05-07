
<?php

interface IPlugin
{
    /**
     * use this function to access plugin configuration variables
     *
     * @param string $setting the setting to access
     * @param mixed $notset what to return if the setting is not available
     * @return mixed
     */
    public function getConf($setting, $notset = false);
    
    /**
     * Access plugin language strings
     *
     * to try to minimise unnecessary loading of the strings when the plugin doesn't require them
     * e.g. when info plugin is querying plugins for information about themselves.
     *
     * @param   string $id id of the string to be retrieved
     * @return  string in appropriate language or english if not available
     */
    public function getLang($id);
}