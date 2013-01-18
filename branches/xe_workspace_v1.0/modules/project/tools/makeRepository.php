#!/usr/local/php/bin/php
<?php
	/**
	 * @file projectXE.php
	 * @brief projectXE script for make svn and account
	 * @author NHN <developers@xpressengine.com>
	 */
    if(isset($_SERVER['SERVER_PROTOCOL'])) die('invalid request');


    define('__ZBXE__', true);
    $path = str_replace('/modules/project/tools/makeRepository.php','',__FILE__);
    require_once($path.'/config/config.inc.php');

    $oContext = &Context::getInstance();
    $oContext->init();
    $oContext->loadLang(_XE_PATH_.'modules/project');

    $oProjectModel = &getModel('project');

    if($argc!=2) die("\nmissing config file.\nUse: php -q makeProject.php PATH/config.ini\n\n");
    $config_file = $argv[1];
    if(!file_exists($config_file)) die("\n\"".$config_file."\" file not founded.\n\n");

    $args = FileHandler::readIniFile($config_file);
    if(substr($args['svn_target'],-1)!='/') $args['svn_target'].='/';

    $output = $oProjectModel->getProjectData();
    $project_config = $oProjectModel->getConfig();
    $server_name_orig = $project_config->repos_url;
    $server_name_arr = explode("/", $server_name_orig);
    $server_name = array_shift($server_name_arr);
    if(count($server_name_arr))
    {
        $location_prefix = "/".implode("/", $server_name_arr);
    }
    else
    {
        $location_prefix = "";
    }
    
    if(count($output)) {
        $vhost_file = $args['vhost_file'];
        $vhost_buff = sprintf("<VirtualHost *:%d>\n\tServerName %s\n\tDocumentRoot %s",
                $args['vhost_port'],
                $server_name,
                $args['document_root']
        );

        foreach($output as $repos_id => $val) {
            $svn_path = $args['svn_target'].$repos_id;
            $passwd_file = $args['svn_target'].$repos_id.'/passwd';
            $svn_auth_file = $args['svn_target'].$repos_id.'/conf/authz';

            // make svn repository if not exists
            if(!is_dir($svn_path)) {
                exec( sprintf('%s create --fs-type fsfs %s', $args['svn'], $svn_path) );

                $files = FileHandler::readDir($svn_path);
                for($i=0,$c=count($files);$i<$c;$i++) @chmod($files[$i],0777);
            }
            if(!is_dir($svn_path)) continue;

            // write http auth user file
            if(count($val->members)) {
                $passwd_buff = null;
                $authz_buff = 'commiter = ';
                foreach($val->members as $user_id => $passwd) {
                    $passwd_buff .= $user_id.":".$passwd."\n";
                    $authz_buff .= $user_id.',';
                }
                $authz_buff = "[groups]\r\n".substr($authz_buff,0,-1)."\n[/]\n@commiter = rw\n* = r\n";
                FileHandler::writeFile($passwd_file, $passwd_buff);
                FileHandler::writeFile($svn_auth_file, $authz_buff);
            }

            // write vhost file
            $vhost_buff .= sprintf("\n\t<Location %s/%s>\n\t\tDAV svn\n\t\tSVNPath %s\n\t\tAuthzSVNAccessFile %s\n\t\tAuthType Basic\n\t\tAuthName \"%s\"\n\t\tAuthUserFile %s\n\t\t<LimitExcept GET PROPFIND OPTIONS REPORT>\n\t\tRequire valid-user\n\t</LimitExcept>\n\t</Location>",
                $location_prefix,
                $repos_id,
                $svn_path,
                $svn_auth_file,
                $val->title,
                $passwd_file
            );
        }
        $vhost_buff .= "\n</VirtualHost>";
        FileHandler::writeFile($args['vhost_file'], $vhost_buff);

    }

    $oContext->close();

?>
