<?php

/**
 * @revision  $Id: class.proxy.php 60 2016-04-01 12:47:26Z aleks $
 * @version  1.0.0 proxy plugin $
 * @package  myorangehost ispmgr plugins
 * @copyright Copyright @ 2016 - All rights reserved.
 * @license  GNU/GPL
 * @author  Alexey Gordeyev IK
 * @author mail aleksej@gordejev.lv
 * @website  http://www.gordejev.lv/
 */

require_once 'class.database.php';


Class Proxy extends DataBase
{
    var $_plugin_path = null;
    var $_mgr_dir = '/usr/local/mgr5/';
    var $_ssl_dir = '/var/www/httpd-cert/';
    var $_ngx_dir = '/etc/nginx/conf.d/';
    var $_xml = '<doc></doc>';

    function __construct($dbfile = '')
    {
        $this->_plugin_path = __DIR__;
        if ($dbfile === '') {
            $dbfile = $this->_mgr_dir . 'etc/' . 'ispmgr.db';
        }
        parent::__construct($dbfile);

        if (!$this->_db) {
            if ($this->_debug) {
                $this->writeLog('Unable to Load SQLite DB ' . $dbfile);
            }
            die('Unable to Load SQLite DB ' . $dbfile);
        } else {
            if ($this->_debug) {
                $this->writeLog('Load SQLite DB ' . $dbfile);
            }
            $createSQL = file_get_contents($this->_plugin_path . '/proxy.sql');
            @$res = $this->_db->query('SELECT * FROM proxies LIMIT 1');
            $err = $this->ErrorNo();
            if ($err) {
                if ($this->_debug) {
                    $this->writeLog($this->ErrorMsg());
                }
                $this->_db->query($createSQL);
                if ($this->_debug) {
                    $this->writeLog($this->ErrorMsg());
                }
            }
        }
    }

    /*
     * XML creating methods
     */

    /**
     * @method getSList
     *
     * @access public
     * @param SimpleXMLElement $doc
     * @param string $name
     * @param array $list
     * @param string $key
     * @return SimpleXMLElement
     */
    function getSList(SimpleXMLElement $doc, $name, $list = array(), $key = 'name')
    {
        $slist = $doc->addChild('slist');
        $slist->addAttribute('name', $name);

        foreach ($list as $item) {
            if (is_array($item)) {
                $option = $slist->addChild('val', $item[$key]);
                $option->addAttribute('key', $item[$key]);
            } elseif (is_string($item)) {
                $option = $slist->addChild('val', $item);
                $option->addAttribute('key', $item);
            }
        }
        return $doc;
    }

    /**
     * @method getProxiesFormPage
     *
     * @access public
     * @param string $domain
     * @param string $owner
     * @return mixed
     */
    function getProxiesFormPage($domain = '', $owner = '')
    {
        $redirect_type = array(
            'noredirect',
            'noflag',
            'last',
            'break',
            'redirect',
            'permanent',
            'proxy'
        );
        $proxy = $this->GetProxy($domain);
        // $users = $this->GetUsers();
        if (is_string($owner) && $owner !== '') {
            $proxy['owner'] = $owner;
        }
        $ssls = $this->GetSSL($proxy['owner']);
        $page = new SimpleXMLElement($this->_xml);

        foreach ($proxy as $key => $val) {
            if ($key === 'ipaddrs') {
                $ipaddrs = explode(",", $val);
                foreach ($ipaddrs as $ipk => $ipaddr) {
                    $page->addChild($key, trim($ipaddr));
                }
            } else {
                $page->addChild($key, $val);
            }
        }

        // $this->getSList($page, 'owner', $users, 'name');
        $this->getSList($page, 'ssl_cert', $ssls);
        $this->getSList($page, 'redirect_type', $redirect_type);

        return $page->asXML();
    }

    /**
     * @method getProxiesListPage
     *
     * @access public
     * @param int $p_num
     * @param int $p_elems
     * @param string $sort
     * @param string $dir
     * @return mixed
     */
    function getProxiesListPage($p_num = 1, $p_elems = 20, $sort = 'domain', $dir = 'asc')
    {
        $offset = $p_num > 1 ? $p_num - 1 * $p_elems : 0;
        $proxies = $this->GetProxies($offset, $p_elems);
        $page = new SimpleXMLElement($this->_xml);

        foreach ($proxies as &$proxy) {
            $elem = $page->addChild('elem');
            foreach ($proxy as $key => $value) {
                $elem->addChild($key, $value);
            }
        }
        if (count($proxies) === 0) {
            $page->addChild('elem');
        }
        return $page->asXML();
    }

    /**
     * @method getOk
     *
     * @access public
     * @return mixed
     */
    function getOk()
    {
        $page = new SimpleXMLElement($this->_xml);
        $page->addChild('ok', '');
        return $page->asXML();
    }

    /*
     * DataBase methods
     */


    /**
     * Select all ispmgr users who is active
     *
     * @method GetUsers
     * @access public
     * @return array
     */
    function GetUsers()
    {
        $this->_sql = 'SELECT id, active, name, fullname FROM users'
            . ' WHERE level = 16 AND active = "on"';
        return $this->loadAssocList();
    }

    /**
     * Get the proxy by domain name
     *
     * @method GetProxy
     * @access public
     * @param  string $domain
     * @return array
     */
    function GetProxy($domain = '')
    {
        $item = array(
            'active' => 'on',
            'elid' => '',
            'curname' => '',
            'owner' => '',
            'redirect_type' => 'proxy',
            'limit_ssl' => "on",
            'secure' => 'off',
            'strict_ssl' => 'off',
            'ssl_port' => 443,
            'redirect_http' => 'off',
            'ddosshield' => 'off'
        );
        $this->writeLog('select domain: ' . $domain . ' ' . is_string($domain) . ' ' . ($domain !== ''));
        if (is_string($domain) && $domain !== '') {
            $this->_sql = 'SELECT * FROM proxies WHERE domain = "' . $domain . '"';
            $item = $this->loadAssoc();
            $item['elid'] = $item['domain'];
        }
        return $item;
    }

    /**
     * Get all the proxies
     *
     * @method GetProxies
     * @access public
     * @param  int $offset
     * @param  int $limit
     * @return array
     */
    function GetProxies($offset = 0, $limit = 0)
    {
        $this->_sql = 'SELECT * FROM proxies';
        $this->_limit = (int)$limit;
        $this->_offset = (int)$offset;
        return $this->loadAssocList();
    }

    /**
     * @method InsertProxy
     * @param object $data
     * @return bool
     */
    function InsertProxy($data)
    {
        $res = $this->insertObject('proxies', $data);
        if ($this->_debug) {
            $this->writeLog($this->ErrorMsg());
        }
        return $res;
    }

    /**
     * @method UpdateProxy
     * @param object $data
     * @return bool
     */
    function UpdateProxy($data)
    {
        if ($data->owner) {
            unset($data->owner);
        }
        $res = $this->updateObject('proxies', $data, 'domain', true);
        if ($this->_debug) {
            $this->writeLog($this->ErrorMsg());
        }
        return $res;
    }

    /**
     * Remove the proxy by domain name
     *
     * @method RemoveProxy
     * @access public
     * @param  string $domain
     * @return array
     */
    function RemoveProxy($domain = '')
    {
        $this->writeLog('remove domain: ' . $domain . ' ' . is_string($domain) . ' ' . ($domain !== ''));
        $res = false;
        if (is_string($domain) && $domain !== '') {
            $this->_sql = 'DELETE FROM proxies WHERE domain = "' . $domain . '"';
            $res = $this->query();
        }
        return $res;
    }

    /**
     * Update LetsEncrypt SSL
     *
     * @param string $domain
     * @return bool|mixed
     */
    function updateLetsEncryptSSL($domain = '')
    {
        $this->writeLog('remove domain: ' . $domain . ' ' . is_string($domain) . ' ' . ($domain !== ''));
        $res = false;
        if (is_string($domain) && $domain !== '') {
            $this->_sql = 'UPDATE letsencrypt_ssl SET can_renew = "on" WHERE domain = "' . $domain . '"';
            $res = $this->query();
        }
        return $res;
    }

    /**
     * Get Param by name
     * @param $name
     * @return mixed
     */
    function getParam($name)
    {
        return $this->$name;
    }

    /**
     * Get the error number
     *
     * @access public
     * @return int The error number for the most recent query
     */
    function getErrorNum()
    {
        return $this->_errorNum;
    }

    /**
     * Get the error message
     *
     * @access public
     * @return string The error message for the most recent query
     */
    function getErrorMsg($escaped = false)
    {
        if ($escaped) {
            return addslashes($this->_errorMsg);
        } else {
            return $this->_errorMsg;
        }
    }

    /**
     * Get a database error log
     *
     * @access public
     * @return array
     */
    function getLog()
    {
        return $this->_log;
    }

    /**
     * Conver array to object
     *
     * @access public
     * @return array
     */
    function array_to_object($array = array())
    {
        if (!empty($array)) {
            $data = false;
            foreach ($array as $key => $val) {
                $data->{$key} = $val;
            }
            return $data;
        }
        return false;
    }

    /*
     * File system methods
     */

    /**
     * @method GetSSL
     * @access public
     * @param  string $user
     * @return array
     */
    function GetSSL($user = '')
    {
        $dirname = $user === '' ? $this->_ssl_dir : $this->_ssl_dir . $user;
        $files = array();
        if ($handle = opendir($dirname)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    $path_parts = pathinfo($file);
                    if (isset($path_parts['extension']) && $path_parts['extension'] === 'crt') {
                        $files[] = str_replace('.crt', '', $file);
                    }
                }
            }
            closedir($handle);
        }
        return $files;
    }

    /**
     * Create Nginx vhost configuration
     * @param $data
     * @return string
     */
    function createNginxConfig($data)
    {
        $nginxFileName = $data['domain'] . ".conf";
        $nginxFolder = "/etc/nginx/vhosts/" . $data['owner'];
        $ownerFolder = '/var/www/' . $data['owner'] . '/data/www/' . $data['domain'];
        $letsFolder = $ownerFolder . '/.well-known';
        $upstreamName = "proxy_" . $data['owner'] . "_" . $data['id'];
        $ipaddrs = explode(",", $data['ipaddrs']);
        $aliases = implode(" ", explode("\n", $data['aliases']));

        if (!file_exists('/etc/nginx/proxy-includes')) {
            mkdir('/etc/nginx/proxy-includes', 0644, true);
        }
        if (!file_exists($nginxFolder)) {
            mkdir($nginxFolder, 0644, true);
        }
        if (!file_exists($ownerFolder)) {
            mkdir($ownerFolder, 0644, true);
            chown($ownerFolder, $data['owner']);
            chgrp($ownerFolder, $data['owner']);
        }
        if (!file_exists($letsFolder)) {
            mkdir($letsFolder, 0644, true);
        }

        $putOut = "#user '" . $data['owner'] . "' proxy host '" . $data['domain'] . "' configuration file";
        $putOut .= "\n\n";

        if ($data['redirect_type'] === 'proxy') {

            $putOut .= "upstream " . $upstreamName . " {\n";
            $putOut .= "    server " . $data['redirect_path'] . ";\n";
            $putOut .= "}\n\n";

            $putOut .= "server {\n";

            if ($data['secure'] === 'on') {
                foreach ($ipaddrs as $ipk => $ipaddr) {
                    $putOut .= "    listen " . trim($ipaddr) . ":" . $data['ssl_port'] . ";\n";
                }
                if ($data['redirect_http'] === 'off') {
                    foreach ($ipaddrs as $ipk => $ipaddr) {
                        $putOut .= "    listen " . trim($ipaddr) . ":80;\n";
                    }
                }
                $putOut .= "    ssl on;\n";
                $putOut .= '    ssl_certificate "/var/www/httpd-cert/' . $data['owner'] . '/' . $data['ssl_cert'] . '.crtca";' . "\n";
                $putOut .= '    ssl_certificate_key "/var/www/httpd-cert/' . $data['owner'] . '/' . $data['ssl_cert'] . '.key";' . "\n";
                if ($data['strict_ssl'] === 'on') {
                    $putOut .= '    ssl_ciphers HIGH:!RC4:!aNULL:!eNULL:!MD5:!EXPORT:!EXP:!LOW:!SEED:!CAMELLIA:!IDEA:!PSK:!SRP:!SSLv2;' . "\n";
                    $putOut .= "    ssl_prefer_server_ciphers on;\n";
                    $putOut .= "    ssl_protocols TLSv1 TLSv1.1 TLSv1.2;\n";
                }
                $this->updateLetsEncryptSSL($data['domain']);
            } else {
                foreach ($ipaddrs as $ipk => $ipaddr) {
                    $putOut .= "    listen " . trim($ipaddr) . ":80;\n";
                }
            }

            $putOut .= "    server_name " . $data['domain'] . " " . $aliases . ";\n";
            $putOut .= "    charset UTF-8;\n\n";
            $putOut .= '    set $root_path /var/www/' . $data['owner'] . '/data/www/' . $data['domain'] . ';' . "\n";
            $putOut .= '    disable_symlinks if_not_owner from=$root_path;' . "\n";
            $putOut .= "    index index.html index.php;\n";
            $putOut .= '    root $root_path;' . "\n";
            $putOut .= "    index index.html index.php;\n\n";

            $putOut .= "    access_log /var/www/httpd-logs/" . $data['domain'] . ".access.log;\n";
            $putOut .= "    error_log /var/www/httpd-logs/" . $data['domain'] . ".error.log notice;\n\n";

            $putOut .= "    location / {\n";
            $putOut .= "        include /etc/nginx/proxy-includes/*.conf;\n\n";
            $putOut .= "        proxy_pass http://" . $upstreamName . "/;\n";
            $putOut .= "        proxy_redirect off;\n";
            $putOut .= "        " . $data['proxy_rules'];
            $putOut .= "    }\n\n";

            $putOut .= "    location ^~ /.well-known {\n";
            $putOut .= "        alias /var/www/" . $data['owner'] . "/data/www/" . $data['domain'] . "/.well-known;\n";
            $putOut .= "    }\n\n";

            $putOut .= "        " . $data['rewrite_rules'];
            $putOut .= "\n\n";

            $putOut .= "}\n\n";

            if ($data['redirect_http'] === 'on') {
                $putOut .= "server {\n";
                foreach ($ipaddrs as $ipk => $ipaddr) {
                    $putOut .= "    listen " . trim($ipaddr) . ":80;\n";
                }
                $putOut .= "    server_name " . $data['domain'] . ";\n";
                $putOut .= '    return 301 https://$host:' . $data['ssl_port'] . '$request_uri;' . "\n";
                $putOut .= "}\n\n";
            }
        } elseif ($data['redirect_type'] === 'permanent') {
            $putOut .= "server {\n";
            foreach ($ipaddrs as $ipk => $ipaddr) {
                $putOut .= "    listen " . trim($ipaddr) . ":80;\n";
            }
            $putOut .= "    server_name " . $data['domain'] . " " . $aliases . ";\n\n";
            $putOut .= '    set $root_path /var/www/' . $data['owner'] . '/data/www/' . $data['domain'] . ';' . "\n";
            $putOut .= '    disable_symlinks if_not_owner from=$root_path;' . "\n";
            $putOut .= "    index index.html index.php;\n";
            $putOut .= '    root $root_path;' . "\n\n";
            $putOut .= '    access_log off;' . "\n";
            $putOut .= '    error_log /dev/null crit;' . "\n";
            $putOut .= '    return 301 http://' . $data['domain'] . ':' . $data['ssl_port'] . '$request_uri permanent;' . "\n";
            $putOut .= "}\n\n";
        }

        $this->writeNginxConfig($nginxFolder, $nginxFileName, $putOut, true);
        exec('nginx -t', $output, $status);
        if ((int)$status === 0) {
            exec('nginx -s reload');
            return '';
        } else {
            $this->removeNginxConfig($data);
            return $output;
        }
    }

    /**
     * Write Nginx Config
     * @param $nginxFolder
     * @param $nginxFileName
     * @param $data
     * @param $putOut
     * @param bool $reset
     */
    function writeNginxConfig($nginxFolder, $nginxFileName, $putOut, $reset = true)
    {
        if ($reset) {
            if (!file_exists($nginxFolder . '/' . $nginxFileName)) {
                unlink($nginxFolder . '/' . $nginxFileName);
            }
        }
        file_put_contents($nginxFolder . '/' . $nginxFileName, $putOut);
    }

    /**
     * Remove nginx vhost configuration files
     * @param $data
     */
    function removeNginxConfig($data)
    {
        $nginxFileName = $data['domain'] . ".conf";
        $nginxFolder = "/etc/nginx/vhosts/" . $data['owner'];
        $ownerFolder = '/var/www/' . $data['owner'] . '/data/www/' . $data['domain'];

        if (!file_exists($nginxFolder . '/' . $nginxFileName)) {
            unlink($nginxFolder . '/' . $nginxFileName);
            exec('nginx -s reload');
        }
        if (!file_exists($ownerFolder)) {
            unlink($ownerFolder);
        }
    }

    /**
     * Create Log rotate config
     * @param $data
     */
    function createLogrotationConfig($data)
    {
        $logName = '/etc/logrotate.d/web/' . $data['domain'];
        $lConfig = '/var/www/httpd-logs/' . $data['domain'] . '.access.log {' . "\n";
        $lConfig .= '   olddir /var/www/' . $data['owner'] . '/data/logs' . "\n";
        $lConfig .= '   rotate 10' . "\n";
        $lConfig .= '   daily' . "\n";
        $lConfig .= '   copytruncate' . "\n";
        $lConfig .= '   compress' . "\n";
        $lConfig .= '}' . "\n\n";
        $lConfig .= '/var/www/httpd-logs/' . $data['domain'] . '.error.log {' . "\n";
        $lConfig .= '   olddir /var/www/' . $data['owner'] . '/data/logs' . "\n";
        $lConfig .= '   rotate 10' . "\n";
        $lConfig .= '   daily' . "\n";
        $lConfig .= '   copytruncate' . "\n";
        $lConfig .= '   compress' . "\n";
        $lConfig .= '}' . "\n";

        file_put_contents($logName, $lConfig);
        exec('logrotate -v -f ' . $logName, $output, $status);
        if ((int)$status === 0) {
            return '';
        } else {
            $this->removeLogrotationConfig($data);
            return $output;
        }
    }

    /**
     * Remove Log rotate config
     * @param $data
     */
    function removeLogrotationConfig($data)
    {
        $logName = '/etc/logrotate.d/web/' . $data['domain'];
        if (!file_exists($logName)) {
            unlink($logName);
        }
    }
}

?>
