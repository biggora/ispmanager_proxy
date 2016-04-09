#!/usr/bin/php
<?php
@set_time_limit(0);
@error_reporting(E_NONE);
@ini_set('display_errors', 0);

require_once __DIR__ . '/../var/proxy/class.proxy.php';

$mgr = new Proxy();
$default_lang = 'ru';
$sqtr = getenv('QUERY_STRING');
$user = getenv('AUTH_USER');
$ok = getenv('PARAM_sok');
$func = getenv('PARAM_func');
$elid = getenv('PARAM_elid');
$owner = getenv('PARAM_owner');

$sqtr = urldecode($sqtr);
parse_str($sqtr, $params);

if(isset($params['elid'])) {
    $elid = $params['elid'];
}

$xml = '<?xml version="1.0" encoding="UTF-8"?><doc></doc>';

$mgr->writeLog('start action: ' . $func);
$mgr->writeLog('querystring: ' . $sqtr);

switch ($func) {
    case 'proxy.edit':

        $row = new stdClass();

        if(isset($owner) && is_string($owner) && $owner !== '') {
            $row->owner = $owner;
        }
        $row->domain = getenv('PARAM_domain');
        $row->aliases = getenv('PARAM_aliases');
        $row->ipaddrs = getenv('PARAM_ipaddrs');
        $row->redirect_path = getenv('PARAM_redirect_path');
        $row->redirect_type = getenv('PARAM_redirect_type');
        $row->rewrite_rules = getenv('PARAM_rewrite_rules');
        $row->proxy_rules = getenv('PARAM_proxy_rules');
        $row->currname = $user;
        /* ddos */
        $row->ddosshield = getenv('PARAM_ddosshield');
        $row->nginx_limitrequest = getenv('PARAM_nginx_limitrequest');
        $row->nginx_burstrequest = getenv('PARAM_nginx_burstrequest');
        /* ssl */
        $row->limit_ssl = getenv('PARAM_limit_ssl');
        $row->secure = getenv('PARAM_secure');
        $row->redirect_http = getenv('PARAM_redirect_http');
        $row->redirect_seo = getenv('PARAM_redirect_seo');
        $row->strict_ssl = getenv('PARAM_strict_ssl');
        $row->ssl_port = getenv('PARAM_ssl_port');
        $row->ssl_cert = getenv('PARAM_ssl_cert');

        /*
         * <ipaddrs>10.10.10.20</ipaddrs>
         */

        if ($ok === 'ok') {
            $old = array();
            if (is_string($elid) && $elid === '') {
                $mgr->InsertProxy($row);
            } else {
                $toremove = $mgr->GetProxy($elid);
                $mgr->removeNginxConfig($toremove);
                $mgr->UpdateProxy($row);
            }
            $tocreate = $mgr->GetProxy($row->domain);
            $mgr->createNginxConfig($tocreate);
            $xml = $mgr->getOk();
        } else {
            $xml = $mgr->getProxiesFormPage($elid , $owner);
        }
        break;
    case 'proxy.delete':
        $toremove = $mgr->GetProxy($elid);
        $mgr->removeNginxConfig($toremove);
        $mgr->RemoveProxy($toremove['domain']);
        $xml = $mgr->getOk();
        break;
    case 'proxy.refresh':

        break;
    case 'proxy.suspend':

        break;
    case 'proxy.resume':

        break;
    default:
        $mgr->writeLog('show list');
        $xml = $mgr->getProxiesListPage(1, 20, 'domain', 'asc');
}
echo $xml;