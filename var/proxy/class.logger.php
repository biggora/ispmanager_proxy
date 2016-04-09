<?php

/**
 * @revision       $Id: class.logger.php 94 2010-08-16 18:18:47Z aleks $
 * @version        1.0.0 proxy plugin $
 * @package        myorangehost ispmgr plugins
 * @copyright      Copyright © 2016 - All rights reserved.
 * @license        GNU/GPL
 * @author         Alexey Gordeyev IK
 * @author mail    aleksej@gordejev.lv
 * @website        http://www.gordejev.lv/
 */
class Logger
{
    var $_debug = true;
    var $_path_to_temp = '/tmp/';
    var $_path_to_logs = '/var/log/';
    var $_log_file_name = 'proxy.log';


    function __construct()
    {

    }

    /**
     * Write string into log file
     *
     * @method writeLog
     * @param $_log
     * @return bool
     */
    function writeLog($_log)
    {
        if (!is_dir($this->_path_to_logs)) {
            mkdir($this->_path_to_logs, 0777, true);
        }
        if (file_put_contents($this->_path_to_logs . '/' . $this->_log_file_name, date('Y-m-d H:i:s') . ' ' . $_log . "\n", FILE_APPEND)) {
            return true;
        } else {
            return false;
        }
    }

}