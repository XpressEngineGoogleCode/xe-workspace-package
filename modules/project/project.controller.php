<?php
    /**
     * @class  projectController
     * @author NHN (developers@xpressengine.com)
     * @brief  project 모듈의 controller class
     **/

    class projectController extends project {

        var $site_module_info = null;
        var $site_srl = null;
        var $project_info = null;

        function init() {
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');

            $logged_info = Context::get('logged_info');
            if( !in_array($this->act,array('procProjectReservation','procProjectReserveImages', 'procProjectUpdateAccount')) && !$oModuleModel->isSiteAdmin($logged_info)) return $this->stop('msg_not_permitted');

            // site_module_info값으로 프로젝트의 정보를 구함
            $this->site_module_info = Context::get('site_module_info');
            $this->site_srl = $this->site_module_info->site_srl;
            $this->project_info = $oProjectModel->getProjectInfo($this->site_srl);
        }

        function procProjectChangeLanguage() {
            $oModuleController = &getController('module');

            $lang_code = Context::get('language');
            if(!$lang_code) return;
            $args->site_srl = $this->site_module_info->site_srl;
            $args->index_module_srl= $this->site_module_info->index_module_srl;
            $args->domain = $this->site_module_info->domain;
            $args->default_language = $lang_code;
            return $oModuleController->updateSite($args);
        }

        function procProjectReservation() {
            global $lang;
            $oProjectAdminController = &getAdminController('project');
            $oProjectModel = &getModel('project');
            $oModuleModel = &getModel('module');
            $oModuleController = &getController('module');
            $oMemberModel = &getModel('member');
            $oMemberController = &getController('member');

            $project_id = strip_tags(Context::get('project_id'));
            if(!$project_id) return new Object(-1,sprintf($lang->filter->isnull, $lang->project_id));
            if(strlen($project_id)<3 || strlen($project_id)>20) return new Object(-1,'alert_project_id_size_is_wrong');
            if($oModuleModel->isIDExists($project_id)) return new Object(-1,'msg_not_enabled_id');
            if(!preg_match('/^([a-z0-9]+)$/i',$project_id)) return new Object(-1,'msg_wrong_project_id');

            $project_title = strip_tags(Context::get('project_title'));
            if(!$project_title) return new Object(-1,sprintf($lang->filter->isnull, $lang->project_title));
            if(strlen($project_title)<3 || strlen($project_title)>20) return new Object(-1,'alert_project_title_size_is_wrong');

            $project_description = strip_tags(Context::get('project_description'));
            if(!$project_description) return new Object(-1,sprintf($lang->filter->isnull, $lang->project_description));
            if(strlen($project_description)<10 || strlen($project_description)>200) return new Object(-1,'alert_project_description_size_is_wrong');

            $logged_info = Context::get('logged_info');

            $args->reserve_srl = getNextSequence();
            $args->site_id = $project_id;
            $args->title = $project_title;
            $args->description = $project_description;
            $args->member_srl = $logged_info->member_srl;
            $output = executeQuery('project.reserveProject', $args);
            if(!$output->toBool()) return $output;

            $vars = Context::getRequestVars();
            if(count($vars)) {
                foreach($vars as $key => $val) {
                    if(strpos($key,'directory_')!==0 || !$val) continue;
                    unset($obj);
                    $obj->site_srl = $args->reserve_srl;
                    $obj->project_dir_srl = $val;
                    $output = executeQuery('project.insertProjectDirMap', $obj);
                }
            }

            $project_config = $oProjectModel->getConfig(0);
            if($project_config->notify_mail) {
                $url = getFullUrl('', 'module','admin', 'act','dispProjectAdminReserved');
                $message  = sprintf(Context::getLang('msg_notify_reserved_content'), $url, $url);
                $message .= 'Project ID : '.$project_id."<br/>";
                $message .= 'Project Title : '.$project_title."<br/>";
                $message .= 'Project Description : '.$project_description."<br/>";
                $this->notify($project_config->notify_mail, Context::getLang('msg_notify_reserved_title'), $message);
            }


            $this->add('reserve_srl', $args->reserve_srl);
        }

        function notify($email_address, $title, $message) {
            $oMail = new Mail();
            $oMail->setTitle($title);
            $oMail->setContent($message);
            $oMail->setSender('XE Project Notifier',$email_address);
            $oMail->setReceiptor( null, $email_address);
            $oMail->send();
        }


        function procProjectReserveImages() {
            $reserve_srl = Context::get('reserve_srl');
            if(!$reserve_srl) return new Object(-1, 'msg_invalid_request');

            $args->reserve_srl = $reserve_srl;
            $output = executeQuery('project.getReserve', $args);
            if(!$output->toBool() || !$output->data) return new Object(-1,'msg_invalid_request');

            $project_logo = Context::get('project_logo');
            if($project_logo['name']) {
                $logo_src = 'files/attach/project_logo/'.$reserve_srl.'.jpg';
				FileHandler::copyfile($project_logo['tmp_name'], $logo_src);
            }

            $project_banner = Context::get('project_banner');
            if($project_banner['name']) {
                $banner_src = 'files/attach/project_banner/'.$reserve_srl.'.jpg';
				FileHandler::copyfile($project_banner['tmp_name'], $banner_src);
            }

            Context::set('redirect_mode', 'reserve');
            Context::set('redirect_url', getFullUrl('','mid', $this->module_info->mid));
            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('redirect.html');
        }

        function procProjectDeleteGroup() {
            $oMemberAdminController = &getAdminController('member');
            $group_srl = Context::get('group_srl');
            $output = $oMemberAdminController->deleteGroup($group_srl, $this->site_srl);
            if(!$output->toBool()) return $output;
        }

        function procProjectInsertGroup() {
            $args->group_srl = Context::get('group_srl');
            $args->title = Context::get('title');
            $args->is_default = Context::get('is_default');
            if($args->is_default!='Y') $args->is_default = 'N';
            $args->description = Context::get('description');
            $args->site_srl = $this->site_srl;

            $oMemberAdminController = &getAdminController('member');
            if($args->group_srl) {
                $output = $oMemberAdminController->updateGroup($args);
            } else {
                $output = $oMemberAdminController->insertGroup($args);
            }
            if(!$output->toBool()) return $output;
        }

        function procProjectDeleteMember() {
            $member_srl = Context::get('member_srl');
            if(!$member_srl) return new Object(-1,'msg_invalid_request');

            $args->site_srl= $this->site_srl;
            $args->member_srl = $member_srl;
            $output = executeQuery('member.deleteMembersGroup', $args);
            if(!$output->toBool()) return $output;
            $this->setMessage('success_deleted');
        }

        function procProjectUpdateMemberGroup() {
            if(!Context::get('cart')) return new Object();
            $args->site_srl = $this->site_srl;
            $args->member_srl = explode('|@|',Context::get('cart'));
            $args->group_srl = Context::get('group_srl');
            $oMemberController = &getController('member');
            return $oMemberController->replaceMemberGroup($args);
        }

        function procProjectInsertBoardGrant() {
            $module_srl = Context::get('module_srl');

            // 현 모듈의 권한 목록을 가져옴
            $oModuleModel = &getModel('module');
            $xml_info = $oModuleModel->getModuleActionXml('board');
            $grant_list = $xml_info->grant;

            if(count($grant_list)) {
                foreach($grant_list as $key => $val) {
                    $group_srls = Context::get($key);
                    if($group_srls) $arr_grant[$key] = explode('|@|',$group_srls);
                }
                $grants = serialize($arr_grant);
            }

            $oModuleController = &getController('module');
            $oModuleController->updateModuleGrant($module_srl, $grants);

            $this->add('module_srl',Context::get('module_srl'));
            $this->setMessage('success_registed');
        }

        function procProjectChangeIndex() {
            $index_mid = Context::get('index_mid');
            if(!$index_mid) return new Object(-1,'msg_invalid_request');
            $args->index_module_srl = $index_mid;
            $args->domain = $this->project_info->domain;
            $args->site_srl= $this->site_srl;

            $oModuleController = &getController('module');
            $output = $oModuleController->updateSite($args);
            return $output;
        }

        function procProjectInsertBanner() {
            global $lang;

            $oProjectModel = &getModel('project');

            $site_srl = Context::get('site_srl');
            if(!$site_srl) return new Object(-1,'msg_invalid_request');

            $title = Context::get('project_title');
            if(!$title) return new Object(-1,sprintf($lang->filter->isnull,$lang->project_title));

            $description = Context::get('project_description');
            if(!$description) return new Object(-1,sprintf($lang->filter->isnull,$lang->project_description));

            // 프로젝트 제목/내용 변경
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            if(!$project_info->site_srl) return new Object(-1,'msg_invalid_request');
            $args->title = $title;
            $args->description = $description;
            $args->site_srl = $project_info->site_srl;
            $output = executeQuery('project.updateProject', $args);
            if(!$output->toBool()) return $output;

            $project_banner = Context::get('project_banner');
            if($project_banner['name']) {
                $banner_src = 'files/attach/project_banner/'.$project_info->site_srl.'.jpg';
				FileHandler::copyfile($project_banner['tmp_name'], $banner_src);
            }

            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('redirect.html');
        }

        function procProjectInsertRepos() {
            $oProjectModel = &getModel('project');
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            $repos_id = Context::get('repos_id');
            if(!$repos_id) return new Object(-1,'msg_invalid_request');

            if(strlen($repos_id)<2 || strlen($repos_id)>20) return new Object(-1,'msg_invalid_request');
            if(!preg_match('/^[a-z]([a-z0-9\_]+)$/i',$repos_id)) return new Object(-1,'msg_invalid_request');
            $repos_id = strtolower($repos_id);

            $config = $oProjectModel->getConfig(0);
            if($config->use_repos!='Y') return new Object(-1,'msg_invalid_request');

            $repos_info = $oProjectModel->getProjectReposInfo($this->site_srl);
            if($repos_info->repos_id) return new Object(-1,'msg_invalid_request');

            $args->site_srl = $this->site_srl;
            $args->repos_srl = getNextSequence();
            $args->repos_id = $repos_id;
            $output = executeQuery('project.insertRepos', $args);
            if(!$output->toBool()) return $output;

            $issue_trackers = $oModuleModel->getModuleSrlByMid('issuetracker');
            $module_srl = $issue_trackers[0];
            if($module_srl) {
                $project_config = $oProjectModel->getConfig(0);
                $vars = $oModuleModel->getModuleExtraVars($module_srl);
                $extra_vars = $vars[$module_srl];
                $url = $project_config->repos_url;
                if(!preg_match('/^http/',$url)) $url = 'http://'.$url;
                if(substr($url,-1)!='/') $url.='/';
                $extra_vars->svn_url = $url.$repos_id;
                $oModuleController->insertModuleExtraVars($module_srl, $extra_vars);
            }

            $this->setMessage('msg_wait_repos_create');
        }

        function procProjectInsertCommitGroup() {
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');

            $allow_commit_group = explode('|@|',Context::get('allow_commit_group'));
            if(!Context::get('allow_commit_group')||!is_array($allow_commit_group)||!count($allow_commit_group)) return new Object(-1,'msg_need_one_group');

            // 프로젝트 정보 업데이트
            $args->site_srl = $this->site_srl;
            $output = executeQuery('project.updateRepos', $args);
            if(!$output->toBool()) return $output;
            // repos_group에 데이터 적용
            $output = executeQueryArray('project.deleteProjectReposGroup', $args);
            if(!$output->toBool()) return $output;

            // 그룹 입력
            for($i=0,$c=count($allow_commit_group);$i<$c;$i++) {
                $args->group_srl = $allow_commit_group[$i];
                $output = executeQueryArray('project.insertProjectReposGroup', $args);
                if(!$output->toBool()) return $output;
            }

            $repos_info = $oProjectModel->getProjectReposInfo($this->site_srl);
            $repos_id = $repos_info->repos_id;

            $issue_trackers = $oModuleModel->getModuleSrlByMid('issuetracker');
            $module_srl = $issue_trackers[0];
            if($module_srl) {
                $project_config = $oProjectModel->getConfig(0);
                $vars = $oModuleModel->getModuleExtraVars($module_srl);
                $extra_vars = $vars[$module_srl];
                $url = $project_config->repos_url;
                if(!preg_match('/^http/',$url)) $url = 'http://'.$url;
                if(substr($url,-1)!='/') $url.='/';
                $extra_vars->svn_url = $url.$repos_id;
                $oModuleController->insertModuleExtraVars($module_srl, $extra_vars);
            }
        }

        function procProjectInsertProjectBanner() {
            global $lang;

            $oProjectModel = &getModel('project');

            $site_srl = Context::get('site_srl');
            if(!$site_srl) return new Object(-1,'msg_invalid_request');

            $title = strip_tags(Context::get('project_title'));
            if(!$title) return new Object(-1,sprintf($lang->filter->isnull,$lang->project_title));
            if(strlen($title)<3 || strlen($title)>20) return new Object(-1,'alert_project_title_size_is_wrong');

            $description = strip_tags(Context::get('project_description'));
            if(!$description) return new Object(-1,sprintf($lang->filter->isnull,$lang->project_description));
            if(strlen($description)<10 || strlen($description)>200) return new Object(-1,'alert_project_description_size_is_wrong');


            // 홈페이지 제목/내용 변경
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            if(!$project_info->site_srl) return new Object(-1,'msg_invalid_request');
            $args->title = $title;
            $args->description = $description;
            $args->site_srl = $project_info->site_srl;
            $output = executeQuery('project.updateProject', $args);
            if(!$output->toBool()) return $output;

			$oModuleModel =& getModel('module');
			$site_info = $oModuleModel->getSiteInfo($project_info->site_srl);
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($site_info->module_srl);
			$oModuleController =& getController('module');
			$module_info->browser_title = $title;
			$output = $oModuleController->updateModule($module_info);
            if(!$output->toBool()) return $output;

            $project_logo = Context::get('project_logo');
            if($project_logo['name']) {
                $logo_src = 'files/attach/project_logo/'.$project_info->site_srl.'.jpg';
				FileHandler::copyfile($project_logo['tmp_name'], $logo_src);
            }

            $project_banner = Context::get('project_banner');
            if($project_banner['name']) {
                $banner_src = 'files/attach/project_banner/'.$project_info->site_srl.'.jpg';
				FileHandler::copyfile($project_banner['tmp_name'], $banner_src);
            }

            // 디렉토리 등록
            $args = Context::getRequestVars();
            if(count($args)) {
                $d_args->site_srl = $project_info->site_srl;
                executeQuery('project.deleteProjectDirMap', $args);
                foreach($args as $key => $val) {
                    if(strpos($key,'directory_')!==0) continue;
                    unset($obj);
                    $obj->site_srl = $project_info->site_srl;
                    $obj->project_dir_srl = $val;
                    executeQuery('project.insertProjectDirMap', $obj);
                }
            }

			$colorset = Context::get('colorset');
			if($colorset)
			{
				$oModuleController =& getController('module');
				$module_config->colorset = $colorset;
				$oModuleController->insertModulePartConfig("project", $project_info->site_srl, $module_config);
			}

            $redirect_url = Context::get('redirect_url') ? Context::get('redirect_url'):NULL;
            if(!empty($redirect_url))
            {
                Context::set('redirect_mode', 'enforce');
                Context::set('redirect_url' , $redirect_url);
            }

            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('redirect.html');
        }

        function triggerProjectAccount() {
            $oMemberController = &getController('member');
            if(!Context::get('is_logged')) return new Object();
            $oMemberController->addMemberMenu('dispProjectAccountManage', 'cmd_manage_project_account');
            return new Object();
        }

        function triggerMemberMenu(&$content) {

            $site_module_info = Context::get('site_module_info');
            $logged_info = Context::get('logged_info');
            if(!$site_module_info->site_srl || !$logged_info->member_srl) return new Object();

            // 프로젝트 설정 메뉴 추가
            if($logged_info->is_admin == 'Y' || $logged_info->is_site_admin) {
                $oMemberController = &getController('member');
                $oProjectModel = &getModel('project');
                $project_info = $oProjectModel->getProjectInfo($site_module_info->site_srl);
                if($project_info->site_srl) $oMemberController->addMemberMenu('dispProjectManage','cmd_project_setup');
            }

            return new Object();
        }

        function _getProjectLayoutInfo()
        {
            //get member count
            $site_module_info = Context::get('site_module_info');
            $c_args->site_srl = $site_module_info->site_srl;
			$output = executeQueryArray("project.getProjectGroupMemberCount", $c_args);
			Context::set('member_groups', $output->data);
			$sum = 0;
			if(!$output->data) $output->data = array();
			foreach($output->data as $group)
			{
				$sum += $group->count;
			}
			Context::set('member_count', $sum);

			//get project_join information
			$logged_info = Context::get('logged_info');
            if(count($logged_info->group_list)) Context::set('project_join', true);
            else Context::set('project_join', false);

            //set is site admin param
            Context::set('isSiteAdmin', $logged_info->is_site_admin);

            //get issue count
            $oModuleModel = &getModel('module');
            $issuetrackerInfo = $oModuleModel->getModuleInfoByMid('issuetracker', $this->module_info->site_srl);

            $oIssueModel = &getModel('issuetracker');
            $issueCount = $oIssueModel->getIssuesCount($issuetrackerInfo->module_srl);
            Context::set('issueCount', $issueCount);

            //add sign.js file
            $oProjectModel = &getModel('project');
            $project_info = $oProjectModel->getProjectInfo($site_module_info->site_srl);

            $project_config = $oProjectModel->getConfig();
            $template_path = sprintf("%sskins/%s/",$this->module_path, $project_config->skin);

            $moduleInfo = Context::get('module_info');

            //get Title
            $act = $this->act ? $this->act:Context::get('act');
            $titleArray = array('dispProjectDescriptionManage' => 'Project Definition',
                                    'dispProjectDescription' => 'Project Definition',
                                    'dispIssuetrackerAdminProjectSetting' => 'Goal',
                                    'dispIssuetrackerOneMilestone' => 'Goal',
                                    'dispIssuetrackerViewMilestone' => 'Goal',
                                    'dispIssuetrackerAdminProjectSettingFront' => 'Goal',
                                    'dispProject' => 'Overview',
                                    'dispProjectManageFront' => 'Setting');
            if(in_array($act, array_keys($titleArray)))
            {

                $pageTitle = $titleArray[$act];
                Context::set('act', $act);
            }
            elseif(in_array($moduleInfo->module,
                        array('guestbook','board')))
            {
                $pageTitle = $moduleInfo->browser_title;
            }
            elseif($moduleInfo->module == 'wiki')
            {
                $pageTitle = ucwords($moduleInfo->module);
            }
            elseif($moduleInfo->module == 'board')
            {
                $pageTitle = $this->browser_title;
            }
            elseif($moduleInfo->module == 'issuetracker')
            {
                $pageTitle = 'Issues';
            }

            if(!empty($pageTitle))
            {
                $hasTitle = TRUE;
                Context::set('pageTitle', $pageTitle);
                Context::set('hasTitle', $hasTitle);
            }

            Context::addJsFile($template_path.'js/sign.js');
        }

        /**
         * @brief 개별 프로젝트의 모든 모듈의 레이아웃 통일 & 관리자일 경우 관리자 메뉴 추가
         **/
        function triggerApplyLayout(&$oModule) {
            global $lang;

            // 팝업 레이아웃이면 패스
            if(!$oModule || $oModule->getLayoutFile()=='popup_layout.html') return new Object();

            // 관리자 페이지이거나 view가 아니라면 패스
            if(Context::get('module')=='admin' || in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) return new Object();

            // 현재 가상사이트가 project이 아닐 경우 pass~
            $site_module_info = Context::get('site_module_info');
            if(!$site_module_info || !$site_module_info->site_srl || $site_module_info->mid != 'project' ) return new Object();

            // 요청된 프로젝트의 정보를 구해서 레이아웃과 관련 정보를 설정
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');
			$project_config = $oProjectModel->getConfig();
			$module_info = $oModuleModel->getModuleInfoByMid($project_config->project_main_mid);
			if($module_info)
			{
				$logged_info = Context::get('logged_info');
				$grant = $oModuleModel->getGrant($module_info, $logged_info);
				if(!$grant->access) return $this->stop('msg_not_permitted');
			}
			if($project_config->menu_srl) {
				$php_file = sprintf('%sfiles/cache/menu/%d.php', _XE_PATH_, $project_config->menu_srl);
				@include($php_file);
				if($menu) {
					Context::set('menu_list', $menu->list);
				}
			}

			$module_info = $oModuleModel->getModuleInfoByModuleSrl($site_module_info->index_module_srl);
			$project_info = $oProjectModel->getProjectInfo($site_module_info->site_srl);
            Context::set('project_info', $project_info);
            $project_config = $oProjectModel->getConfig();
            Context::set('project_config', $project_config);
            $project_site_config = $oProjectModel->getConfig($site_module_info->site_srl);
            Context::set('project_site_config', $project_site_config);

            if($project_config->skin == 'xe_pacakgePro') $this->_getProjectLayoutInfo();

            // 일단 레이아웃을 있음으로 변경
            Context::set('layout',null);

            $my_args->member_srl = $logged_info->member_srl;
            $output = executeQueryArray('project.getMyProjects', $my_args);
            Context::set('my_repos', $output->data);

            // 프로젝트 스킨 디렉토리 구함
			if(Mobile::isFromMobilePhone()) {
				if(!$module_info->mskin) $module_info->mskin="default";
				$template_path = sprintf("%sm.skins/%s/",$this->module_path, $module_info->mskin);
			}
			else
			{
				$template_path = sprintf("%sskins/%s/",$this->module_path, $project_config->skin);
			}
            $oModule->module_info->layout_srl = null;
            $oModule->setLayoutPath($template_path);
            $oModule->setLayoutFile('project_layout');

			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				$margs->member_srl = $logged_info->member_srl;
				$output = executeQueryArray('project.getMyProjects', $margs);
				if(!$output->data) $output->data = array();
				$this->my_projects = $output->data;
				Context::set('my_projects', $output->data);
			}

            return new Object();
        }

        function procProjectUpdateAccount() {
            $logged_info = Context::get('logged_info');
            $password = Context::get('password');
            if(!$logged_info->member_srl || !$password) return new Object(-1,'msg_invalid_request');

            $args->member_srl = $logged_info->member_srl;
            $args->passwd = crypt($password, substr($password,0,2));
            $output = executeQuery('project.deleteReposAccount', $args);
            if(!$output->toBool()) return $output;
            $output = executeQuery('project.insertReposAccount', $args);
            if(!$output->toBool()) return $output;

            $output = executeQueryArray('project.getMemberRepos', $args);
            if($output->data && count($output->data)) {
                $site_srls = array();
                foreach($output->data as $key => $val) {
                    $site_srls[] = $val->site_srl;
                }
                if(count($site_srls)) {
                    $s_args->site_srl = implode(',',$site_srls);
                    $output = executeQuery('project.updateRepos', $s_args);
                    if(!$output->toBool()) return $output;
                }
            }
            $this->setMessage('success_saved');
        }



		function triggerInsertDocument(&$obj) {
			if($obj->is_secret == "Y") return new Object();
			$site_module_info = Context::get('site_module_info');
			if(!$site_module_info->site_srl) return;

			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($site_module_info->site_srl);
			if(!$project_info) return new Object();

			$args->site_srl = $site_module_info->site_srl;
			$args->target_srl = $obj->document_srl;
			$args->type = "d";
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
			executeQuery("project.insertNewItem", $args);

			$output = executeQuery("issuetracker.getIssue", $args);
			if($output->data && $output->data->assignee_srl && $output->data->assignee_srl != $args->member_srl)
			{
				$args2 = clone($args);
				$args2->member_srl = $output->data->assignee_srl;
				$args2->type ="a";
				$output2 = executeQuery("project.insertNewItem", $args2);
			}

			if(!$logged_info) return new Object();
			$args->member_srl = $logged_info->member_srl;
			$this->addContribute($args);
			return new Object();
		}

		function addContribute($args)
		{
			$output = executeQuery("project.getContribute", $args);
			if(!$output->data)
			{
				$output = executeQuery("project.insertContribute", $args);
			}
			else
			{
				$output = executeQuery("project.increaseContribute", $args);
			}
		}

		function triggerDeleteDocument(&$obj) {
			$this->_deleteNewItem($obj->document_srl);
			return new Object();
		}

		function triggerInsertComment(&$obj) {
			if($obj->is_secret == "Y") return new Object();
            if(!$obj->content || !trim($obj->content)) return new Object();
			$site_module_info = Context::get('site_module_info');
			if(!$site_module_info->site_srl) return;

			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($site_module_info->site_srl);
			if(!$project_info) return new Object();
			$args->site_srl = $site_module_info->site_srl;
			$args->target_srl = $obj->comment_srl;
			$args->type = "c";
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery("project.insertNewItem", $args);
			if(!$logged_info) return new Object();
			$args->member_srl = $logged_info->member_srl;
			$this->addContribute($args);
			return new Object();
		}

		function triggerDeleteComment(&$obj) {
			$this->_deleteNewItem($obj->comment_srl);
			return new Object();
		}

		function _deleteNewItem($target_srl)
		{
			$args->target_srl = $target_srl;
			$output = executeQuery("project.deleteNewItem", $args);
		}

		function triggerInsertRelease(&$obj) {
			$module_srl = $obj->module_srl;
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($module_info->site_srl);
			if(!$project_info) return;
			$args->site_srl = $module_info->site_srl;
			$args->target_srl = $obj->release_srl;
			$args->type = "D";
			$output = executeQuery("project.insertNewItem", $args);
			$args->member_srl = 0;
			$this->addContribute($args);
			return new Object();
		}

		function triggerDeleteRelease(&$obj) {
			$args->target_srl = $obj->release_srl;
			$output = executeQuery("project.deleteNewItem", $args);
			$output = executeQuery("project.deleteDailyCnt", $args);
			return new Object();
		}

		function triggerAddMemberToGroup(&$obj) {
			if(!$obj->site_srl) return;
			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($obj->site_srl);
			if(!$project_info) return;

			$args = clone($obj);
			$args->member_srl = 0;
			$args->type = "m";
			$this->addContribute($args);
			return new Object();
		}

		function triggerInsertChangeset(&$obj) {
			$module_srl = $obj->module_srl;
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($module_info->site_srl);
			if(!$project_info) return;

			$oMemberModel =& getModel('member');
			$member_info = $oMemberModel->getMemberInfoByUserId($obj->author);
			if(!$member_info) return;

			$args->site_srl = $module_info->site_srl;
			$args->target_srl = $obj->revision;
			$args->type = "s";
			$args->member_srl = $member_info->member_srl;
			$args->regdate = $obj->date;
			$output = executeQuery("project.insertNewItem", $args);

			$this->addContribute($args);
			return new Object();
		}

		function triggerFileDownload(&$obj) {
			$module_srl = $obj->module_srl;
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($module_info->site_srl);
			if(!$project_info) return;
            $args->release_srl = $obj->upload_target_srl;
            $output = executeQuery("project.getReleases", $args);
            if(!$output->data) return;

			$args->site_srl = $module_info->site_srl;
			$args->target_srl = $obj->upload_target_srl;
			$args->regdate = date("Ymd");
			$args->type = "D";
			$output = executeQuery("project.getDailyCnt", $args);
			if($output->data)
			{
				$output =executeQuery("project.increaseDailyCnt", $args);
			}
			else
			{
				$output =executeQuery("project.insertDailyCnt", $args);
			}
			return new Object();
		}

		function triggerInsertHistory(&$obj) {
			if(!$obj->history) return;
			$module_srl = $obj->module_srl;
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($module_info->site_srl);
			if(!$project_info) return;

			$args->comment_srls = $obj->comment_srl;
			$args->type = "a";
			$output = executeQuery("issuetracker.getHistoryChanges", $args);
			if(!$output->data) return;
			if(is_array($output->data)) $output->data = array_shift($output->data);
			if(!$output->data->after) return;

			$args = null;
			$args->target_srl = $obj->target_srl;
			$args->type = "a";
			$args->member_srl = $output->data->after;
			$args->site_srl = $project_info->site_srl;
			$output = executeQuery("project.insertNewItem", $args);
			return new Object();
		}

        function triggerMoveDocumentModule(&$obj)
        {
			$module_srl = $obj->module_srl;
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

			$args->target_srls = $obj->document_srls;
			$output = executeQueryArray("project.getCommentsWithDocument", $args);
			if($output->data)
			{
				$comment_srls = array();
				foreach($output->data as $data)
				{
					$comment_srls[] = $data->comment_srl;
				}
				$args->target_srls .= ",".implode(",",$comment_srls);
			}

			$oProjectModel =& getModel('project');
			$project_info = $oProjectModel->getProjectInfo($module_info->site_srl);
			if(!$project_info) {
				$output = executeQuery("project.deleteNewItems", $args);
			}
			else
			{
				$args->site_srl = $project_info->site_srl;
				$output = executeQuery("project.updateNewItems", $args);
			}

			return new Object();
        }

		function recalculateContribute() {
            return;
			$args->type="a";
			$output = executeQueryArray("project.getNewItemsInType", $args);
			foreach($output->data as $data)
			{
				$args = null;
				$args->issues_history_srls = $data->target_srl;
				$output2 = executeQuery("project.getIssueHistories", $args);
				if($output2->data)
				{
					$args->orig_target_srl = $data->target_srl;
					$args->target_srl = $output2->data->document_srl;
					$output2 = executeQuery("project.updateNewItem", $args);
				}
			}
            return;
			$output = executeQuery("project.deleteContribute");
			$output = executeQueryArray("project.getProjects");
			foreach($output->data as $project)
			{
				$args = null;
				$args->site_srl = $project->site_srl;
				$output2 = executeQueryArray("project.getCommentCount", $args);
				if($output2->data)
				{
					foreach($output2->data as $data)
					{
						$args2 = null;
						$args2->member_srl = $data->member_srl;
						$args2->site_srl = $data->site_srl;
						$args2->type = "c";
						$args2->cnt = $data->count;
						executeQuery("project.insertContribute", $args2);
					}
				}

				$output2 = executeQueryArray("project.getDocumentCount", $args);
				if($output2->data)
				{
					foreach($output2->data as $data)
					{
						$args2 = null;
						$args2->member_srl = $data->member_srl;
						$args2->site_srl = $data->site_srl;
						$args2->type = "d";
						$args2->cnt = $data->count;
						executeQuery("project.insertContribute", $args2);
					}
				}

				$output2 = executeQueryArray("project.getRevisionCount", $args);
				if($output2->data)
				{
					foreach($output2->data as $data)
					{
						$args2 = null;
						$args2->member_srl = $data->member_srl;
						$args2->site_srl = $data->site_srl;
						$args2->type = "s";
						$args2->cnt = $data->count;
						executeQuery("project.insertContribute", $args2);
					}
				}

				$output2 = executeQuery("project.getMemberCount", $args);
				if($output2->data)
				{
					$args2 = null;
					$args2->member_srl = 0;
					$args2->site_srl = $args->site_srl;
					$args2->cnt = $output2->data->count;
					$args2->type ="m";
					$output3 = executeQuery("project.insertContribute", $args2);
				}

				$output2 = executeQuery("project.getReleaseCounts", $args);
				if($output2->data)
				{
					$args2 = null;
					$args2->member_srl = 0;
					$args2->site_srl = $args->site_srl;
					$args2->cnt = $output2->data->count;
					$args2->type ="D";
					$output3 = executeQuery("project.insertContribute", $args2);
				}
			}
		}
    }
?>
