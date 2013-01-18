<?php
    /**
     * @class  projectAdminView
     * @author NHN (developers@xpressengine.com)
     * @brief  project 모듈의 admin view class
     **/

    class projectAdminView extends project {

        function init() {
            $template_path = sprintf("%stpl/",$this->module_path);
            $this->setTemplatePath($template_path);
        }

        function dispProjectAdminContent() {
            $oProjectModel = &getModel('project');
            $oModuleModel = &getModel('module');
            $oMemberModel = &getModel('member');

            // 생성된 프로젝트 목록을 구함
            $page = Context::get('page');
            $output = $oProjectModel->getProjectList($page);

            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('project_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('index');
        }

        function dispProjectAdminReserved() {
            $oProjectModel = &getModel('project');

            $page = Context::get('page');
            $args->page = $page;
            $output = executeQueryArray('project.getReservedList', $args);

            if($output->data) {
                foreach($output->data as $key => $val) {
                    unset($dargs);
                    unset($dout);
                    $dargs->reserve_srl = $val->reserve_srl;
                    $dout = executeQueryArray('project.getReserveDirMap', $dargs);
                    if($dout->data) foreach($dout->data as $dir) $val->directories[] = $dir->project_dir_srl;
                    else $val->directories = array();
                    $output->data[$key] = $val;
                }
            }

            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('reserved_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);

            $directories = $oProjectModel->getProjectDirectories(0);
            if(count($directories)) {
                foreach($directories as $key => $val) {
                    $directories[$key]->childs = $oProjectModel->getProjectDirectories($key);
                }
            }
            Context::set('directories', $directories);

            Context::addJsFilter($this->module_path.'tpl/filter', 'user_create_project.xml');
            $this->setTemplateFile('reserved');

        }

        function dispProjectAdminCreate() {
            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);
            $this->setTemplateFile('create');
        }

        function dispProjectAdminConfig() {
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');
            $oMemberModel = &getModel('member');
            $oLayoutModel = &getModel('layout');

            // 프로젝트 메인 스킨 설정
            Context::set('skins', $oModuleModel->getSkins($this->module_path));

			$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);

            // project 전체 설정을 구함
            $project_config = $oProjectModel->getConfig();

            // 메인모듈의 layout_srl을 구함
            $module_info = $oModuleModel->getModuleInfoByMid($project_config->project_main_mid,0);

            $project_config->layout_srl = $module_info->layout_srl;
            Context::set('project_config', $project_config);

            // 서비스 모듈을 구함
            $installed_module_list = $oModuleModel->getModulesXmlInfo();
            foreach($installed_module_list as $key => $val) {
                if($val->category != 'service') continue;
                if($val->module == 'issuetracker') continue;
                $service_modules[] = $val;
            }
            Context::set('service_modules', $service_modules);

            // 기본 사이트의 그룹 구함
            $groups = $oMemberModel->getGroups(0);
            Context::set('groups', $groups);

            // 레이아웃 목록을 구함
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

			$oMenuAdminModel =& getAdminModel('menu');
			Context::set('menu_list', $oMenuAdminModel->getMenus());

            $this->setTemplateFile('config');
        }

        function dispProjectAdminDirectorySetup() {
            $oProjectModel = &getModel('project');

            // 등록된 디렉토리 가져오기
            Context::set('directories', $list = $oProjectModel->getProjectDirectories(0));

            // 하부 디렉토리가 선택되어 있으면 가져오기
            $project_dir_srl = Context::get('project_dir_srl');
            if($project_dir_srl) {
                $directory = $list[$project_dir_srl];
                if($directory) {
                    Context::set('directory', $list[$project_dir_srl]);
                    Context::set('sub_directories', $oProjectModel->getProjectDirectories($project_dir_srl));
                } else {
                    Context::set('project_dir_srl','');
                }
            }

            $this->setTemplateFile('directory_setup');
        }

        function dispProjectAdminDirectoryModify() {
            $args->project_dir_srl = Context::get('project_dir_srl');
            $output = executeQuery('project.getProjectDirectory', $args);
            if(!$output->toBool()||!$output->data) return new Object(-1,'msg_invalid_request');

            Context::set('directory', $output->data);
            $this->setTemplateFile('directory_modify');
        }

        function dispProjectAdminSetup() {
            $oProjectAdminModel = &getAdminModel('project');
            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');

            $site_srl = Context::get('site_srl');
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            Context::set('project_info', $project_info);

            // project 전체 설정을 구함
            $project_config = $oProjectModel->getConfig();
            $module_info = $oModuleModel->getModuleInfoByMid($project_config->project_main_mid, $site_srl);
            Context::set('module_info', $module_info);

            // 서비스 모듈을 구함
            $installed_module_list = $oModuleModel->getModulesXmlInfo();
            foreach($installed_module_list as $key => $val) {
                if($val->category != 'service') continue;
                if($val->module == 'issuetracker') continue;
                $service_modules[] = $val;
            }
            Context::set('service_modules', $service_modules);

            $oModuleModel = &getModel('module');
            $admin_list = $oModuleModel->getSiteAdmin($site_srl);
            Context::set('admin_list', $admin_list);

            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

            $this->setTemplateFile('setup');
        }

        function dispProjectAdminSkinSetup() {
            $oModuleAdminModel = &getAdminModel('module');
            $oProjectModel = &getModel('project');

            $project_config = $oProjectModel->getConfig(0);
            $skin_content = $oModuleAdminModel->getModuleSkinHTML($project_config->module_srl);
            Context::set('skin_content', $skin_content);

            $this->setTemplateFile('skin_info');
        }

        function dispProjectAdminDelete() {
            $site_srl = Context::get('site_srl');
            $oProjectModel = &getModel('project');
            $project_info = $oProjectModel->getProjectInfo($site_srl);
            Context::set('project_info', $project_info);

            $oModuleModel = &getModel('module');
            $admin_list = $oModuleModel->getSiteAdmin($site_srl);
            Context::set('admin_list', $admin_list);

            $this->setTemplateFile('delete');
        }

		function dispProjectAdminGrantInfo()
		{
			$oProjectModel =& getModel('project');
			$project_config = $oProjectModel->getConfig();
			$oModuleModel =& getModel('module');
			$module_info = $oModuleModel->getModuleInfoByMid($project_config->project_main_mid);
			if(!$module_info) return;

            $oModuleAdminModel = &getAdminModel('module');
            $grant_content = $oModuleAdminModel->getModuleGrantHTML($module_info->module_srl, $this->xml_info->grant);
            Context::set('grant_content', $grant_content);

            $this->setTemplateFile('grant_list');
		}
    }

?>
