<?php
    /**
     * @class  projectAdminController
     * @author NHN (developers@xpressengine.com)
     * @brief  project 모듈의 admin controller class
     **/

    class projectAdminController extends project {

        function init() {
        }

        /**
         * @brief 프로젝트 설정
         **/
        function procProjectAdminInsertConfig($vars = NULL) {
            global $lang;

            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');

            if(!$vars) $vars = Context::getRequestVars();

            if($vars->site_srl) {
                $config = $oModuleModel->getModulePartConfig('project', $vars->site_srl);
                $config->use_rss = $vars->use_rss;
                $config->use_repos = $vars->use_repos;
                $config->repos_url = $vars->repos_url;
                $oModuleController->insertModulePartConfig('project', $vars->site_srl, $config);

                $mid = Context::get('mid') ? Context::get('mid'):
                        $var->mid ? $var->mid:'project';
                $module_info = $oModuleModel->getModuleInfoByMid($mid, $vars->site_srl);
                $module_info->skin = Context::get('skin') ? Context::get('skin'):$module_info->skin;
                $module_info->site_srl = $var->site_srl;
                $output = $oModuleController->updateModule($module_info);
                if(!$output->toBool()) return $output;
            }else {
                $args->use_rss = $vars->use_rss;
                $args->use_repos = $vars->use_repos;
                $args->repos_url = $vars->repos_url;
			    $args->creation_agreement = $vars->creation_agreement;
                $args->access_type = $vars->access_type;
                $args->default_domain = $vars->default_domain;
                $args->notify_mail = $vars->notify_mail;
                if($args->default_domain && strpos($args->default_domain,':')!==false) $args->default_domain = preg_replace('/^(http|https):\/\//i','',$args->default_domain);
                if($args->default_domain && substr($args->default_domain,-1)!='/') $args->default_domain .= '/';
                if($args->access_type != 'vid' && !$args->default_domain) return new Object(-1,sprintf($lang->filter->isnull, $lang->domain));

                $args->project_main_mid = $vars->project_main_mid;
                $args->browser_title = $vars->browser_title;
                if(!$args->browser_title) $args->browser_title = 'projectXE';
                if(!$args->project_main_mid) return new Object(-1,sprintf($lang->filter->isnull,$lang->project_main_mid));
                $args->skin = $vars->skin;
                if(!$args->skin) $args->skin = 'xe_project';

				$args->mskin = $vars->mskin;
				if(!$args->mskin) $args->mskin = 'default';

				$args->use_mobile = $vars->use_mobile;
				if(!$args->use_mobile) $args->use_mobile = "N";

				$output = executeQueryArray("project.getProjects");
				if($output->data) {
					$args_m->use_mobile = $args->use_mobile;
					$site_srls = array();
					foreach($output->data as $project)
					{
						$site_srls[] = $project->site_srl;
					}
					$oModuleController->updateModuleInSites(implode(",",$site_srls), $args_m);
				}

                $project_config = $oProjectModel->getConfig(0);
                $mid = $project_config->project_main_mid;
                $layout_srl = $vars->layout_srl;
				$mlayout_srl = $vars->mlayout_srl;
                $module_info = $oModuleModel->getModuleInfoByMid($mid, 0);
                if(!$module_info->module_srl) {
                    $module_args->site_srl = 0;
                    $module_args->mid = $args->project_main_mid;
                    $module_args->skin = $args->skin;
					$module_args->mskin = $args->mskin;
					$module_args->use_mobile = $args->use_mobile;
                    $module_args->browser_title = $args->browser_title;
                    $module_args->module = 'project';
                    $module_args->layout_srl = $layout_srl;
					$module_args->mlayout_srl = $mlayout_srl;
                    $output = $oModuleController->insertModule($module_args);
                    if(!$output->toBool()) return $output;
                } else {
                    $module_args->module = 'project';
                    $module_args->mid = $args->project_main_mid;
                    $module_args->skin = $args->skin;
					$module_args->mskin = $args->mskin;
					$module_args->use_mobile = $args->use_mobile;
                    $module_args->site_srl = 0;
                    $module_args->browser_title = $args->browser_title;
                    $module_args->module_srl = $module_info->module_srl;
                    $module_args->layout_srl = $layout_srl;
					$module_args->mlayout_srl = $mlayout_srl;
                    $output = $oModuleController->updateModule($module_args);
                    if(!$output->toBool()) return $output;
                }

                $module_info = $oModuleModel->getModuleInfoByMid($mid, 0);
                $args->module_srl = $module_info->module_srl;
				$args->menu_srl = $vars->menu_srl;
                $oModuleController->insertModuleConfig('project', $args);
            }
        }

        function procProjectAdminCreation() {
            global $lang;
            $oProjectModel = &getModel('project');
            $oModuleModel = &getModel('module');
            $oModuleController = &getController('module');
            $oMemberModel = &getModel('member');
            $oMemberController = &getController('member');

            $reserve_srl = Context::get('reserve_srl');
            if(!$reserve_srl) return new Object(-1, 'msg_invalid_request');

            $args->reserve_srl = $reserve_srl;
            $output = executeQuery('project.getReserve', $args);
            if(!$output->toBool() || !$output->data) return new Object(-1,'msg_invalid_request');
            $reserve = $output->data;

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

            $project_config = $oProjectModel->getConfig();
            if($project_config->access_type == 'vid') $domain = $project_id;
            else {
               if(substr($project_config->default_domain,0,1)!='.') $project_config->default_domain = '.'.$project_config->default_domain;
               $domain = $project_id.$project_config->default_domain;
            }

            $output = $this->insertProject($project_title, $domain);
            if(!$output->toBool()) return $output;

            $site_srl = $this->get('site_srl');

            // 프로젝트 제목/내용 변경
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            $args->title = $project_title;
            $args->description = $project_description;
            $args->site_srl = $site_srl;
            $output = executeQuery('project.updateProject', $args);
            if(!$output->toBool()) return $output;

            $default_group = $oMemberModel->getDefaultGroup($site_srl);
            $oMemberController->addMemberToGroup($reserve->member_srl, $default_group->group_srl, $site_srl);

            $member = $oMemberModel->getMemberInfoByMemberSrl($reserve->member_srl);

            $output = $oModuleController->insertSiteAdmin($site_srl, array($member->user_id));

            $r_args->reserve_srl = $reserve_srl;
            $output = executeQuery('project.deleteReserve', $r_args);

            $r_args->site_srl = $site_srl;
            $output = executeQuery('project.updateDirMap', $r_args);

            $source_logo_src = 'files/attach/project_logo/'.$reserve_srl.'.jpg';
            $target_logo_src = 'files/attach/project_logo/'.$site_srl.'.jpg';
            FileHandler::rename($source_logo_src, $target_logo_src);

            $source_banner_src = 'files/attach/project_banner/'.$reserve_srl.'.jpg';
            $target_banner_src = 'files/attach/project_banner/'.$site_srl.'.jpg';
            FileHandler::rename($source_banner_src, $target_banner_src);
        }

        function procProjectAdminDeleteReserve() {
            $reserve_srl = Context::get('reserve_srl');
            if(!$reserve_srl) return new Object(-1, 'msg_invalid_request');

            $args->reserve_srl = $reserve_srl;
            $output = executeQuery('project.getReserve', $args);
            if(!$output->toBool() || !$output->data) return new Object(-1,'msg_invalid_request');

            $output = executeQuery('project.deleteReserve', $args);

            $args->site_srl = $reserve_srl;
            $output = executeQuery('project.deleteProjectDirMap', $args);

            $source_logo_src = 'files/attach/project_logo/'.$reserve_srl.'.jpg';
            FileHandler::removeFile($source_logo_src);

            $source_banner_src = 'files/attach/project_banner/'.$reserve_srl.'.jpg';
            FileHandler::removeFile($source_banner_src);
        }


        /**
         * @brief 접속 방법중 domain 이나 site id나 모두 sites 테이블의 domain 컬럼에 저장이 됨
         * site id보다 domain이 우선 순위를 가짐
         **/
        function procProjectAdminInsertProject($title = NULL, $vid = NULL, $projectSkin = 'xe_pacakgePro') {
            if(!$title)$title = Context::get('title');
            if(Context::get('skin')) $projectSkin = Context::get('skin');

            $domain = preg_replace('/^(http|https):\/\//i','', trim(Context::get('domain')));
            if(!$vid)$vid = trim(Context::get('site_id'));

            if($domain && $vid) unset($vid);
            if(!$domain && $vid) $domain = $vid;

            if(!$title) return new Object(-1, 'msg_invalid_request');
            if(!$domain) return new Object(-1, 'msg_invalid_request');

            $output = $this->insertProject($title, $domain, $projectSkin);
            return $output;
        }

        function insertProject($title, $domain, $projectSkin = 'xe_pacakgePro') {
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            $info->title = $title;
            $info->domain = $domain;

            // virtual site 생성하고 site_srl을 보관
            $output = $oModuleController->insertSite($domain, 0);
            if(!$output->toBool()) return $output;
            $info->site_srl = $output->get('site_srl');

            // 언어 코드 추출
            // 언어 코드 등록 (홈, 공지사항, 등업신청, 자유게시판, 전체 글 보기, 한줄이야기, 프로젝트앨범, 메뉴등)
            $langs = Context::loadLangSupported();
            $path = './modules/project/lang/';
            global $lang;
            $selectedLang = Context::getLangType();
            foreach($langs as $langKey => $langWord) {
                $file = $path.'lang.xml';
                $oXmlLangParser = new XmlLangParser($file, $langKey);
                $file = $oXmlLangParser->compile();
                if($file)
                {
                    include($file);
                }
                else
                {
                    $content = $oXmlLangParser->getCompileContent();
                    if($content)
                    {
                        eval($content);
                    }
                }
                if($langKey == $selectedLang)
                {
                    $selectedFile = $file;
                }
                if(count($lang->project_member_group)) {
                    foreach($lang->project_member_group as $key => $val)
                    {
                            unset($lang_args);
                            $lang_args->site_srl = $info->site_srl;
                            $lang_args->name = $key;
                            $lang_args->lang_code = $langKey;
                            $lang_args->value = $val;
                    }
                    executeQuery('module.insertLang', $lang_args);
                }
            }

            //include the default lang file
            include($selectedFile);

            $oModuleAdminController = &getAdminController('module');
            $oModuleAdminController->makeCacheDefinedLangCode($info->site_srl);

            // 프로젝트 레이아웃 지정
            $oProjectModel = &getModel('project');
            $project_config = $oProjectModel->getConfig(0);

            // 이슈트래커, 포럼 게시판, 위키 생성
            $info->module->project_srl = $this->makeModule('project', $projectSkin, $info->site_srl, 'project', $title);

            $info->module->forum_srl = $this->makeModule('board', 'xe_package_forum', $info->site_srl, 'forum', 'Forum');
            $forum_skin_vars->colorset = 'white';
            $forum_skin_vars->default_style = 'forum';
            $forum_skin_vars->display_setup_button = 'Y';
            $forum_skin_vars->title = 'Forum';
            $forum_skin_vars->comment = Context::getLang('about_forum_description');
            $forum_skin_vars->display_ip_address = 'Y';
            $forum_skin_vars->display_sign = 'Y';
            $forum_skin_vars->thumbnail_type = 'crop';
            $oModuleController->insertModuleSkinVars($info->module->forum_srl, $forum_skin_vars);
            $oModuleController->insertModulePartConfig('board', $info->module->forum_srl, array('no','title','nick_name','regdate','readed_count','last_post'));

            $info->module->issuetracker_srl = $this->makeModule('issuetracker', 'xe_package', $info->site_srl, 'issuetracker', 'Issuetracker');
            $info->module->wiki_srl = $this->makeModule('wiki', 'xe_package', $info->site_srl, 'wiki', 'Wiki');

            //insert guestbook
            $project_info = $oProjectModel->getProjectInfo($info->site_srl);
            $this->makeModule('guestbook', 'xe_package', $info->site_srl, 'packageMessage', 'Message');

            $rank_output = executeQuery('project.getMaxRank');
            $args->rank  = $rank_output->data->rank+1;

            // 프로젝트 등록
            $args->site_srl = $info->site_srl;
            $args->title = $info->title;
            $args->list_order = $info->site_srl * -1;
            $output = executeQuery('project.insertProject', $args);

            // site의 index_module_srl 을 변경
            $site_args->site_srl = $info->site_srl;
            $site_args->domain = $domain;
            $site_args->index_module_srl = $info->module->project_srl;
            $oModuleController->updateSite($site_args);

            // 기본그룹 추가
            $oMemberAdminController = &getAdminController('member');
            unset($args);
            $args->title = '$user_lang->observer';
            $args->is_default = 'Y';
            $args->is_admin = 'N';
            $args->site_srl = $info->site_srl;
            $oMemberAdminController->insertGroup($args);

            unset($args);
            $args->title = '$user_lang->regular';
            $args->is_default = 'N';
            $args->is_admin = 'N';
            $args->site_srl = $info->site_srl;
            $oMemberAdminController->insertGroup($args);

            unset($args);
            $args->title = '$user_lang->commiter';
            $args->is_default = 'N';
            $args->is_admin = 'N';
            $args->site_srl = $info->site_srl;
            $oMemberAdminController->insertGroup($args);

			$oMemberModel =& getModel('member');
            $default_group = $oMemberModel->getDefaultGroup($info->site_srl);
			$logged_info = Context::get('logged_info');
            $oMemberController = &getController('member');
            $oMemberController->addMemberToGroup($logged_info->member_srl, $default_group->group_srl, $info->site_srl);

            // 기본 애드온 On
            $oAddonController = &getAdminController('addon');
            $oAddonController->doInsert('autolink', $info->site_srl);
            $oAddonController->doInsert('counter', $info->site_srl);
            $oAddonController->doInsert('member_communication', $info->site_srl);
            $oAddonController->doInsert('member_extra_info', $info->site_srl);
            $oAddonController->doInsert('referer', $info->site_srl);
            $oAddonController->doInsert('resize_image', $info->site_srl);
            $oAddonController->doInsert('wiki_link', $info->site_srl);
            $oAddonController->doActivate('autolink', $info->site_srl);
            $oAddonController->doActivate('counter', $info->site_srl);
            $oAddonController->doActivate('member_communication', $info->site_srl);
            $oAddonController->doActivate('member_extra_info', $info->site_srl);
            $oAddonController->doActivate('referer', $info->site_srl);
            $oAddonController->doActivate('resize_image', $info->site_srl);
            $oAddonController->doActivate('wiki_link', $info->site_srl);
            $oAddonController->doActivate('captcha', $info->site_srl);
            $oAddonController->makeCacheFile($info->site_srl);

            // 기본 에디터 컴포넌트 On
            $oEditorController = &getAdminController('editor');
            $oEditorController->insertComponent('colorpicker_text',true, $info->site_srl);
            $oEditorController->insertComponent('colorpicker_bg',true, $info->site_srl);
            $oEditorController->insertComponent('emoticon',true, $info->site_srl);
            $oEditorController->insertComponent('url_link',true, $info->site_srl);
            $oEditorController->insertComponent('image_link',true, $info->site_srl);
            $oEditorController->insertComponent('multimedia_link',true, $info->site_srl);
            $oEditorController->insertComponent('quotation',true, $info->site_srl);
            $oEditorController->insertComponent('table_maker',true, $info->site_srl);
            $oEditorController->insertComponent('poll_maker',true, $info->site_srl);
            $oEditorController->insertComponent('image_gallery',true, $info->site_srl);

            $this->add('site_srl', $info->site_srl);
            $this->add('url', getSiteUrl($info->domain, ''));
            return new Object();
        }

        function makeModule($module, $skin, $site_srl, $mid, $browser_title) {
            $args->site_srl = $site_srl;
            $args->module_srl = getNextSequence();
            $args->module = $module;
            $args->mid = $mid;
            $args->browser_title = $browser_title;
            $args->is_default = 'N';
            $args->skin = $skin;

            $oModuleController = &getController('module');
            $output = $oModuleController->insertModule($args);
            return $output->get('module_srl');
        }

        function procProjectAdminUpdateProject() {
            $oProjectModel = &getModel('project');
            $oModuleController = &getController('module');

            // 프로젝트이름, 접속방법, 프로젝트관리자 지정
            $args = Context::gets('site_srl','title','project_admin');
            if(!$args->site_srl) return new Object(-1,'msg_invalid_request');

            if(Context::get('access_type')=='domain') $args->domain = Context::get('domain');
            else $args->domain = Context::get('vid');
            if(!$args->domain) return new Object(-1,'msg_invalid_request');

            $project_info = $oProjectModel->getProjectInfo($args->site_srl);
            if(!$project_info->site_srl) return new Object(-1,'msg_invalid_request');

            // 관리자 지정
            $admin_list = explode(',',$args->project_admin);
            $output = $oModuleController->insertSiteAdmin($args->site_srl, $admin_list);
            if(!$output->toBool()) return $output;

            // 프로젝트이름 변경
            $output = executeQuery('project.updateProject', $args);
            if(!$output->toBool()) return false;

            // 도메인 변경
            $output = $oModuleController->updateSite($args);
            if(!$output->toBool()) return $output;

            // 기본 레이아웃, 레이아웃 변경, 허용 서비스 변경
            $this->procProjectAdminInsertConfig();

            $this->setMessage('success_updated');
        }

        function procProjectAdminDeleteProject() {
            $site_srl = Context::get('site_srl');
            if(!$site_srl) return new Object(-1,'msg_invalid_request');

            $oProjectModel = &getModel('project');
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            if(!$project_info->site_srl) return new Object(-1,'msg_invalid_request');

            $args->site_srl = $site_srl;

            // 프로젝트 정보 삭제
            executeQuery('project.deleteProject', $args);

            // 사이트 정보 삭제
            executeQuery('module.deleteSite', $args);

            // 사이트 관리자 삭제
            executeQuery('module.deleteSiteAdmin', $args);

            // 회원 그룹 매핑 데이터 삭제
            executeQuery('member.deleteMemberGroup', $args);

            // 회원 그룹 삭제
            executeQuery('member.deleteSiteGroup', $args);

            // 매핑 삭제
            executeQuery('project.deleteMap', $args);

            // 카운터 정보 삭제
            $oCounterController = &getController('counter');
            $oCounterController->deleteSiteCounterLogs($site_srl);

            // 애드온 삭제
            $oAddonController = &getController('addon');
            $oAddonController->removeAddonConfig($site_srl);

            // 에디터 컴포넌트 삭제
            $oEditorController = &getController('editor');
            $oEditorController->removeEditorConfig($site_srl);

            // 모듈 삭제
            $oModuleModel = &getModel('module');
            $oModuleController =&getController('module');
            $mid_list = $oModuleModel->getMidList($args);
            foreach($mid_list as $key => $val) {
                $module_srl = $val->module_srl;
                $oModuleController->deleteModule($module_srl);
            }

            // 사용자 정의 언어 제거
            $lang_args->site_srl = $site_srl;
            $output = executeQuery('module.deleteLangs', $lang_args);
            $lang_supported = Context::get('lang_supported');
            foreach($lang_supported as $key => $val) {
                $lang_cache_file = _XE_PATH_.'files/cache/lang_defined/'.$site_srl.'.'.$key.'.php';
                FileHandler::removeFile($lang_cache_file);
            }

            $this->setMessage('success_deleted');
        }

        /**
         * @brief 다른 가상 사이트에서 모듈을 이동
         **/
        function procProjectAdminImportModule() {
            $oModuleModel = &getModel('module');
            $oModuleController = &getController('module');
            $oProjectModel = &getModel('project');

            $module_srl = Context::get('import_module_srl');
            $site_srl = Context::get('site_srl');
            if(!$module_srl || !$site_srl) return new Object(-1,'msg_invalid_request');

            $site_module_info = $oModuleModel->getSiteInfo($site_srl);
            if(!$site_module_info->site_srl) return new Object(-1,'msg_invalid_request');

            $project_info = $oProjectModel->getProjectInfo($site_srl);

            $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
            if(!$module_info->module_srl) return new Object(-1,'msg_invalid_request');

            if($module_info->site_srl == $site_srl) return new Object(-1,'msg_same_site');

            // 대상 모듈의 site_srl을 변경
            $output = $oModuleController->updateModuleSite($module_srl, $site_srl);
            if(!$output->toBool()) return $output;
        }

        /**
         * @brief 가상 사이트의 모듈을 기본 사이트로 이동
         **/
        function procProjectAdminExportModule() {
            $oModuleModel = &getModel('module');
            $oModuleController = &getController('module');
            $oProjectModel = &getModel('project');

            $module_srl = Context::get('export_module_srl');
            if(!$module_srl) return new Object(-1,'msg_invalid_request');

            $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
            if(!$module_info->module_srl || !$module_info->site_srl) return new Object(-1,'msg_invalid_request');

            $site_srl = $module_info->site_srl;
            $site_module_info = $oModuleModel->getSiteInfo($site_srl);
            if(!$site_module_info->site_srl) return new Object(-1,'msg_invalid_request');

            $project_info = $oProjectModel->getProjectInfo($site_srl);

            // 대상 모듈의 site_srl을 변경
            $output = $oModuleController->updateModuleSite($module_srl, 0, '');
            if(!$output->toBool()) return $output;
        }

        /**
         * @brief 디렉토리 생성
         **/
        function procProjectAdminInsertDirectory() {
            $args = Context::gets('project_dir_srl', 'title','description','parent_directory_srl');
            if(!$args->project_dir_srl) {
                $args->project_dir_srl = getNextSequence();
                $args->list_order = $args->project_dir_srl*-1;
                return executeQuery('project.insertDirectory', $args);
            } else {
                executeQuery('project.updateDirectory', $args);
            }
        }

        /**
         * @brief 디렉토리 삭제
         **/
        function procProjectAdminDeleteDirectory() {
            $args->project_dir_srl = Context::get('project_dir_srl');
            if(!$args->project_dir_srl) return new Object(-1,'msg_invalid_request');
            return executeQuery('project.deleteDirectory', $args);
        }
    }

?>
