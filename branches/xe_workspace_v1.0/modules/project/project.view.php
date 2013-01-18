<?php
    /**
     * @class  projectView
     * @author NHN (developers@xpressengine.com)
     * @brief  project 모듈의 view class
     **/

    class projectView extends project {

        var $site_module_info = null;
        var $site_srl = 0;
        var $project_info = null;
		var $my_projects = null;
		var $sort_order = null;

        /**
         * @brief 초기화
         **/
        function init() {
            global $lang;

            $oModuleModel = &getModel('module');
            $oProjectModel = &getModel('project');

            // 가상 사이트 정보 설정
            $this->site_module_info = Context::get('site_module_info');
            $this->site_srl = $this->site_module_info->site_srl;

            // 프로젝트 정보를 추출하여 세팅
            $this->project_info = $oProjectModel->getProjectInfo($this->site_srl);
            Context::set('project_info', $this->project_info);
        	if($this->site_srl)
        	{
        		$project_site_config = $oProjectModel->getConfig($this->site_srl);
        		Context::set('project_site_config', $project_site_config);
        	}

    		$project_config = $oProjectModel->getConfig();
    		if($project_config->menu_srl) {
    			$php_file = sprintf('%sfiles/cache/menu/%d.php', _XE_PATH_, $project_config->menu_srl);
    			@include($php_file);
    			if($menu) {
    				Context::set('menu_list', $menu->list);
    			}
    		}
    		$module_info = $oModuleModel->getModuleInfoByMid($project_config->project_main_mid,$this->site_srl);
    		if($module_info)
    		{
    			$logged_info = Context::get('logged_info');
    			$grant = $oModuleModel->getGrant($module_info, $logged_info);
    			if(!$grant->access) return $this->stop('msg_not_permitted');

                $template_path = sprintf("%sskins/%s/",$this->module_path, $module_info->skin);
                if(!is_dir($template_path)||!$module_info->skin) {
                    $module_info->skin = 'xe_project';
                    $template_path = sprintf("%sskins/%s/",$this->module_path, $module_info->skin);
                }
                $this->setTemplatePath($template_path);
    		}

            // 메인페이지가 아니고 Project Action인 경우 사이트 관리 권한이 없으면 접근 금지
            if(!in_array($this->act, array('dispProjectDescription','dispProjectMyProjectActivity', 'dispProjectMyActivity', 'dispProjectContributors', 'dispProjectPlan', 'dispProjectMyPlan', 'dispProjectSummary', 'dispProjectMySummary', 'dispProjectDownloadSearch', 'dispProjectSearch', 'dispProjectIndex','dispProjectMember','dispProjectCreateProject','dispProjectAccountManage')) && strpos($this->act,'Project')!==false) {
                // 현재 접속 권한 체크하여 사이트 관리자가 아니면 접근 금지
                $logged_info = Context::get('logged_info');
                if(!Context::get('is_logged') || !$oModuleModel->isSiteAdmin($logged_info)) return $this->stop('msg_not_permitted');

                // site_module_info값으로 프로젝트의 정보를 구함
                if(!$this->site_srl) return $this->stop('msg_invalid_request');

                // 프로젝트 관리 기능이므로 tpl 디렉토리를 템플릿 디렉토리로 설정
                $template_path = sprintf("%stpl",$this->module_path);
                $this->setTemplatePath($template_path);

                // 모듈 번호가 있으면 해동 모듈의 정보를 구해와서 세팅
                $module_srl = Context::get('module_srl');
                if($module_srl) {
                    $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
                    if(!$module_info || $module_info->site_srl != $this->site_srl) return new Object(-1,'msg_invalid_request');
                    $this->module_info = $module_info;
                    Context::set('module_info', $module_info);
                }
            }

			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				$margs->member_srl = $logged_info->member_srl;
				$margs->s_keyword = Context::get('s_keyword');
				$output = executeQueryArray('project.getMyProjects', $margs);
				if(!$output->data) $output->data = array();
				$this->my_projects = $output->data;
				Context::set('my_projects', $output->data);
			}
        }

		function dispProjectMyActivity()
		{
            $logged_info = Context::get('logged_info');
            if(!$logged_info)
            {
				Context::set('act','dispProjectSummary');
                return $this->dispProjectSummary();
            }

			$oModel =& getModel('project');
			$page = Context::get('page');
			if(!$page)
			{
				$page = 1;
				Context::set('page',$page);
			}
			$site_module_info = Context::get('site_module_info');
			if($site_module_info && $site_module_info->site_srl)
			{
				$site_srl = $site_module_info->site_srl;
			}

			if(!$site_srl) {
				$site_srl = Context::get('site_srl');
				if(!$site_srl) unset($site_srl);
			}

			if($site_srl){
				$oModuleModel = &getModel('module');
				$siteInfo = $oModuleModel->getSiteInfo($site_srl);
				Context::set('project_title', $siteInfo->browser_title);
			}

			$type = Context::get('type');
			$output = $oModel->getNewItems($page, $site_srl, $logged_info->member_srl, $type);
			$counts = $oModel->getNewItemsCounts($site_srl, $logged_info->member_srl);
			Context::set("counts", $counts);
			$sum = 0;
			foreach($counts as $item)
			{
				$sum += $item;
			}
			Context::set('sum_activities', $sum);
			if(count($output->sites) > 0)
			{
				Context::set('projects', $oModel->getProjects(implode(",", $output->sites)));
			}

			Context::set('modules', $output->modules);
            Context::set('page_navigation', $output->page_navigation);
			Context::set('activities', $output->data);

			$this->setTemplateFile("myactivity.html");
		}

		function dispProjectMyProjectActivity()
		{
			$this->dispProjectMyActivity();
			$this->setTemplateFile("myprojectactivity.html");
		}

        /**
         * @brief 프로젝트 index
         * site_srl 이 있으면 개별 프로젝트, 아니면 프로젝트 메인을 출력
         **/
        function dispProjectIndex() {
            $site_module_info = Context::get('site_module_info');
            if(!$site_module_info->site_srl) $this->dispProjectMain();
            else $this->dispProject();
        }

        /**
         * @brief 프로젝트 메인
         **/
        function dispProjectMain() {
            $logged_info = Context::get('logged_info');
            if(!$logged_info)
            {
				Context::set('act','dispProjectSummary');
                return $this->dispProjectSummary();
            }
            else
            {
				Context::set('act','dispProjectMySummary');
                return $this->dispProjectMySummary();
            }
        }

		function dispProjectSearch() {
            $oProjectModel = &getModel('project');
            Context::set('directories', $oProjectModel->getProjectAllDirectories());

            $page = Context::get('page');
            if(!$page) {
                $page = 1;
                Context::set('page', $page);
            }
			$sort_order = Context::get('sort_order');
			if(!$sort_order) {
				$sort_order = 'point';
				Context::set('sort_order', $sort_order);
			}

			$search_keyword = Context::get('search_keyword');
			$project_dir_srl = Context::get('project_dir_srl');
            $output = $oProjectModel->getProjectListInSummary($page, $sort_order, 10, $search_keyword, $project_dir_srl);
            Context::set('page_navigation', $output->page_navigation);
			Context::set('project_list', $output->data);


			$this->setTemplateFile('project_search');
		}

		function dispProjectDownloadSearch() {
          $oProjectModel = &getModel('project');
          $directories = $oProjectModel->getProjectAllDirectories();
          $args = Context::getRequestVars();
          Context::set('directories', $directories);

          $val = NULL;
          $gKey = array();
          foreach($directories as $key => $val)
          {
              $gKey = 'dir_'.$key;
              if($args->$gKey)
              {
                  $dirVal[$gKey] = $args->$gKey;
              }
          }
          Context::set('dirVal', $dirVal);

          $page = Context::get('page');
          if(!$page) {
              $page = 1;
              Context::set('page', $page);
          }
		  $sort_order = Context::get('sort_order');
		  if(!$sort_order) {
			$sort_order = 'download_count';
			Context::set('sort_order', $sort_order);
		  }
		  $output = $oProjectModel->getReleaseList($sort_order, $page, $dirVal, $args->keyword );
         Context::set('page_navigation', $output->page_navigation);
		  Context::set('project_list', $output->data);
		  Context::set('keyword', $args->keyword);

		  $this->setTemplateFile('download_search');
		}

		function dispProjectMyPlan() {
			$site_srls = array();
			foreach($this->my_projects as $val)
			{
				$site_srls[] = $val->site_srl;
			}
			$this->dispProjectPlan(implode(",", $site_srls));
		}

		function dispProjectPlan($site_srls = null) {
			$startdate = Context::get('startdate');
			$enddate = Context::get('enddate');
			$oModel =& getModel('project');
			if($startdate)
			{
				$aftermore = false;
				$list = $oModel->getIssueMilestones($startdate, null, "asc", 10, $aftermore, $site_srls);
				Context::set('milestones', $list);
				Context::set('aftermore', $aftermore);
				Context::set('beforemore', true);
			}
			else if($enddate)
			{
				$beforemore = false;
				$enddate = date("Ymd", ztime($enddate) - 24*60*60);
				$list = $oModel->getIssueMilestones(null, $enddate, "desc", 10, $beforemore, $site_srls);
				Context::set('milestones', $list);
				Context::set('aftermore', true);
				Context::set('beforemore', $beforemore);
			}
			else
			{
				$startdate = date("Ymd");
				$beforemort = false;
				$aftermore = false;
				$list1 = $oModel->getIssueMilestones($startdate, null, "asc", 5, $aftermore, $site_srls);
				$list2 = $oModel->getIssueMilestones(null, $startdate, "desc", 5, $beforemore, $site_srls);
				Context::set('aftermore', $aftermore);
				Context::set('beforemore', $beforemore);
				$list = array_merge($list2, $list1);
				Context::set('milestones', $list);
			}

			$this->setTemplateFile('project_plan');
		}

		function dispPopularDownloads() {
			$args->today = date("Ymd");
			$args->weekago = date("Ymd", ztime(date("Ymd")) - 24*60*60*7);
            $args->list_count = 20;
			$output = executeQueryArray("project.getPopularDownloads", $args);
			if(!$output->data) return;
			$release_srls = array();
			foreach($output->data as $data)
			{
				$release_srls[] = $data->release_srl;
			}
			$args->release_srls = implode(",", $release_srls);
			$output2 = executeQueryArray("project.getReleases", $args);
			$releases = array();
            if(!$output2->data) return;
			foreach($output2->data as $data)
			{
				$releases[$data->release_srl] = $data;
			}

			foreach($output->data as $key=>$data)
			{
                $item = $releases[$data->release_srl];
                if(!$item->package_title) {
                    unset($output->data[$key]);
                    continue;
                }
				$output->data[$key]->item =  $releases[$data->release_srl];
                $list[] = $output->data[$key];
                if(count($list)>9) break;
			}
			Context::set('popular_downloads', $list);
		}

        function _dispProjectListAll($list_count = 5)
        {
            $oProjectModel =& getModel('project');
            $page = Context::get('page');
            if(!$page) {
                $page = 1;
                Context::set('page', $page);
            }
			$sort_order = Context::get('sort_order');
			if(!$sort_order) {
				$sort_order = 'point';
				Context::set('sort_order', $sort_order);
			}

            $output = $oProjectModel->getProjectListInSummary($page, $sort_order, $list_count);
            Context::set('page_navigation', $output->page_navigation);
			Context::set('project_list', $output->data);
        }

        function dispProjectSummary() {
            $oProjectModel =& getModel('project');
            $page = Context::get('page');
            if(!$page) {
                $page = 1;
                Context::set('page', $page);
            }
			$sort_order = Context::get('sort_order');
			if(!$sort_order) {
				$sort_order = 'point';
				Context::set('sort_order', $sort_order);
			}

			$search_keyword = Context::get('s_keyword');
            $output = $oProjectModel->getProjectListInSummary($page, $sort_order, 10, $search_keyword);
            Context::set('page_navigation', $output->page_navigation);

			//set images-logo,banner infomation
            $output->data = $this->_getImageInfo($output->data);

            //get project site admin
            $output->data = $this->_getSiteAdmin($output->data);

			Context::set('project_list', $output->data);

			$this->dispPopularDownloads();

			if($page == 1 && $sort_order == "regdate")
			{
				$output2 = $output;
			}
			else
			{
				$output2 = $oProjectModel->getProjectList(1, null, 0, 5, "regdate");
			}
			Context::set('recent_project_list', $output2->data);

            $page = Context::get('news_page');
            if(!$page) {
                $page = 1;
                Context::set('news_page', $page);
            }


			$output = $oProjectModel->getNewItems($page);
			if(count($output->sites) > 0)
			{
				Context::set('projects', $oProjectModel->getProjects(implode(",", $output->sites)));
			}

			Context::set('modules', $output->modules);
            Context::set('news_page_navigation', $output->page_navigation);
			Context::set('news_list', $output->data);

            $this->setTemplateFile('project_summary');
        }

		function cmp_target($a, $b)
		{
			if( $a->{$this->sort_order} == $b->{$this->sort_order} ) return 0;
			return ( $a->{$this->sort_order} > $b->{$this->sort_order} ) ? -1 : 1;
		}

		function _dispProjectList($list_count = 5) {
            $oProjectModel =& getModel('project');
			$logged_info = Context::get('logged_info');
			if(!$logged_info) return $this->dispProjectSummary();
            $args->member_srl = $logged_info->member_srl;
            $page = Context::get('page');
            if(!$page) {
                $page = 1;
                Context::set('page', $page);
            }

			$sort_order = Context::get('sort_order');
			if(!$sort_order)
			{
				$sort_order = "point";
				Context::set('sort_order',$sort_order);
			}
			$this->sort_order = $sort_order;

			$output = executeQueryArray("project.getActivityPoints", $args);
			if(!$output->data) $output->data = array();
			$apoints = array();
			foreach($output->data as $data)
			{
				$apoints[$data->site_srl] = $data->point;
			}

			$site_srls = array();
			foreach($this->my_projects as $key=>$project)
			{
				$this->my_projects[$key]->point = $apoints[$project->site_srl]?$apoints[$project->site_srl]:0;
				$site_srls[] = $project->site_srl;
			}
			$args->site_srl = implode(",", $site_srls);
			$output = executeQueryArray("project.getProjectMemberCount", $args);
			if(!$output->data) $output->data = array();
			$member_counts = array();
			foreach($output->data as $data)
			{
				$member_counts[$data->site_srl] = $data->count;
			}

			foreach($this->my_projects as $key=>$project)
			{
				$this->my_projects[$key]->member_count = $member_counts[$project->site_srl]?$member_counts[$project->site_srl]:0;
			}

			usort( $this->my_projects, array( $this, "cmp_target" ) );
			$projects = array_slice($this->my_projects, ($page-1)*$list_count, $list_count);

            $output = $oProjectModel->getProjectList($page, null, 0, $list_count, 'rank', $args->member_srl);
			$total_count = count($this->my_projects);
            Context::set('page_navigation', new PageHandler($total_count, (int)(($total_count+$list_count-1)/$list_count), $page));
			Context::set('project_list', $projects);
		}

		function _getImageInfo($projects)
		{
		    $oProjectModel =& getModel('project');
		    foreach($projects as $key => $project)
			{
			    $imageInfo = $oProjectModel->getProjectImages($project->site_srl);
			    $projects[$key]->project_logo = $imageInfo->project_logo;
			    $projects[$key]->project_banner = $imageInfo->project_banner;
			}
			return $projects;
		}

		function _getSiteAdmin($projects)
		{
            $oModuleModel = &getModel('module');
		    foreach($projects as $key => $project)
			{
			    $siteAdmin = $oModuleModel->getSiteAdmin($project->site_srl);
			    $adminInfos = array();
			    foreach($siteAdmin as $adminInfo)
			    {
			        $adminInfos[] = $adminInfo->nick_name;
			    }
			    $projects[$key]->siteAdmin = implode(',', $adminInfos);
			}
            return $projects;
		}

        //project hub my project items
        function dispProjectMySummary() {
            $oProjectModel =& getModel('project');
			$logged_info = Context::get('logged_info');
			if(!$logged_info) return $this->dispProjectSummary();
            $args->member_srl = $logged_info->member_srl;
            $page = Context::get('page');
            if(!$page) {
                $page = 1;
                Context::set('page', $page);
            }

			$sort_order = Context::get('sort_order');
			if(!$sort_order)
			{
				$sort_order = "point";
				Context::set('sort_order',$sort_order);
			}
			$this->sort_order = $sort_order;

			$output = executeQueryArray("project.getActivityPoints", $args);
			if(!$output->data) $output->data = array();
			$apoints = array();
			foreach($output->data as $data)
			{
				$apoints[$data->site_srl] = $data->point;
			}


			$site_srls = array();
			foreach($this->my_projects as $key=>$project)
			{
				$this->my_projects[$key]->point = $apoints[$project->site_srl]?$apoints[$project->site_srl]:0;
				$site_srls[] = $project->site_srl;
			}
			$args->site_srl = implode(",", $site_srls);
			$output = executeQueryArray("project.getProjectMemberCount", $args);
			if(!$output->data) $output->data = array();
			$member_counts = array();
			foreach($output->data as $data)
			{
				$member_counts[$data->site_srl] = $data->count;
			}

			foreach($this->my_projects as $key=>$project)
			{
				$this->my_projects[$key]->member_count = $member_counts[$project->site_srl]?$member_counts[$project->site_srl]:0;
			}

			usort( $this->my_projects, array( $this, "cmp_target" ) );
			$list_count = 5;
			$projects = array_slice($this->my_projects, ($page-1)*$list_count, $list_count);

            $output = $oProjectModel->getProjectList($page, null, 0, 5, 'rank', $args->member_srl);
			$total_count = count($this->my_projects);
            Context::set('page_navigation', new PageHandler($total_count, ($total_count+$list_count-1)/$list_count, $page));

			//set images-logo,banner infomation
            $projects = $this->_getImageInfo($projects);

            //get project site admin
            $projects = $this->_getSiteAdmin($projects);

			Context::set('project_list', $projects);

			$output2 = $oProjectModel->getProjectList(1, null, 0, 5, "regdate");
			Context::set('recent_project_list', $output2->data);

            $page = Context::get('news_page');
            if(!$page) {
                $page = 1;
                Context::set('news_page', $page);
            }

			$output = $oProjectModel->getNewItems($page, implode(",", $site_srls));
			if(count($output->sites) > 0)
			{
				Context::set('projects', $oProjectModel->getProjects(implode(",", $output->sites)));
			}

			Context::set('modules', $output->modules);
            Context::set('news_page_navigation', $output->page_navigation);
			Context::set('news_list', $output->data);

			$this->dispPopularDownloads();

            $this->setTemplateFile('mysummary');
        }

		/**
		 * @brief project 목록을 넘겨 받아 배너/로고 이미지와 회원수를 세팅
		 */
		function setProjectInfo($list) {
			if(!$list || !count($list)) return array();
			foreach($list as $key => $val) {
				$logo_src = 'files/attach/project_logo/'.$val->site_srl.'.jpg';
				if(file_exists(_XE_PATH_.$logo_src)) $list[$key]->project_logo = $logo_src.'?rnd='.filemtime(_XE_PATH_.$logo_src);
				else $list[$key]->project_logo = $this->module_path.'tpl/img/projectLogo.gif';

				$banner_src = 'files/attach/project_banner/'.$val->site_srl.'.jpg';
				if(file_exists(_XE_PATH_.$banner_src)) $list[$key]->project_banner = $banner_src.'?rnd='.filemtime(_XE_PATH_.$banner_src);
				else $list[$key]->project_banner = $this->module_path.'tpl/img/projectBanner.gif';

				$site_srl_list[$val->site_srl] = $key;
			}
			if(count($site_srl_list)) {
				$c_args->site_srl = implode(',',array_keys($site_srl_list));
				$c_output = executeQueryArray('project.getProjectMemberCount', $c_args);
				if($c_output->data) {
					foreach($c_output->data as $key => $val) {
						$list[$site_srl_list[$val->site_srl]]->member_count = $val->count;
					}
				}
			}
			return $list;
		}

        /**
         * @brief 프로젝트 생성
         **/
        function dispProjectCreateProject() {
            $oProjectModel = &getModel('project');
			if(!$this->grant->creation) return new Object(-1,'alert_permission_denied_to_create');

            $project_config = $oProjectModel->getConfig($this->site_srl);
            Context::set('project_config', $project_config);

            Context::set('directories', $oProjectModel->getProjectAllDirectories());

            Context::addJsFilter($this->module_path.'tpl/filter', 'user_reserve_project.xml');
            $this->setTemplateFile('create');
        }


		//project home page
		function dispProject() {
            $oModuleModel = &getModel('module');
            Context::set('site_admins', $oModuleModel->getSiteAdmin($this->site_srl));
			Context::set('act', 'dispProject');

            $oProjectModel = &getModel('project');

            $project_config = $oProjectModel->getConfig(0);
            if($project_config->use_repos == 'Y')
            {
                $repos_info = $oProjectModel->getProjectReposInfo($this->site_srl);
                if($repos_info->repos_id)
                {
                    $repos_url = sprintf("http://%s/%s", $project_config->repos_url, $repos_info->repos_id);
                    Context::set("repos_url", $repos_url);
                }
            }

			$c_args->site_srl = $this->site_srl;
			$output = executeQueryArray("project.getProjectGroupMemberCount", $c_args);
			Context::set('member_groups', $output->data);
			$sum = 0;
			if(!$output->data) $output->data = array();
			foreach($output->data as $group)
			{
				$sum += $group->count;
			}
			Context::set('member_count', $sum);

			//get the member list
			$oProjectModel = &getModel('project');
			$output = $oProjectModel->getMemberList();
            Context::set('member_list', $output->data);

			$output = executeQueryArray("project.getMilestonesHome", $c_args);
			if($output->data)
			{
				$oIssuetrackerModel =& getModel('issuetracker');
                foreach($output->data as $key => $milestone) {
					$issues = $oIssuetrackerModel->getIssueCountByStatus($milestone->milestone_srl, $milestone->module_srl);
                    $output->data[$key]->issues = $issues;
                }
				$output->data = array_reverse($output->data);
				Context::set('milestones', $output->data);
			}


			$page = Context::get('page');
			if(!$page) {
				$page = 1;
				Context::set('page', $page);
			}
			$output = $oProjectModel->getNewItems($page, $this->site_srl);
			Context::set('modules', $output->modules);
			Context::set('news_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			$output = executeQueryArray("project.getNotices", $c_args);
			if($output->data)
			{
				$documents = array();
				$oDocumentModel =& getModel('document');
				foreach($output->data as $data)
				{
					$oDocument = $oDocumentModel->getDocument();
					$oDocument->setAttribute($data, false);
					$documents[] = $oDocument;
				}
				Context::set('notices', $documents);
			}

            $logged_info = Context::get('logged_info');
            if(count($logged_info->group_list)) Context::set('project_join', true);
            else Context::set('project_join', false);

            $this->setTemplateFile('project_home');
		}

		function dispProjectDescription()
		{
		    $oProjectModel = &getModel('project');

            $project_config = $oProjectModel->getConfig($this->site_srl);
            Context::set('project_site_config', $project_config);
            $this->setTemplateFile('project_description');
		}

		function dispProjectDescriptionManage()
		{
		    $oProjectModel = &getModel('project');

            $project_config = $oProjectModel->getConfig($this->site_srl);
            $project_config2 = $oProjectModel->getConfig();
            Context::set('project_site_config', $project_config);

            // 디렉토리 구함
            $directories = $oProjectModel->getProjectDirectories(0);

            // 각각의 서브디렉토리 구함
            if(count($directories)) {
                foreach($directories as $key => $val) {
                    $directories[$key]->childs = $oProjectModel->getProjectDirectories($key);
                }
            }
            Context::set('directories', $directories);
			$oModuleModel =& getModel('module');
			$skin_info = $oModuleModel->loadSkinInfo($this->module_path, $project_config2->skin);
			if($skin_info->colorset)
			{
				Context::set('colorset', $skin_info->colorset);
			}

            $oModuleModel = &getModel('module');


            $this->setTemplateFile('project_description_setup');
		}

		function dispProjectManageFront()
		{
		    $this->_getManageInfo();
		    $this->setTemplateFile('project_setup_front');
		}

		function _getManageInfo()
		{
		    $oProjectModel = &getModel('project');

            $project_config = $oProjectModel->getConfig($this->site_srl);
            $project_config2 = $oProjectModel->getConfig();
            Context::set('project_site_config', $project_config);

            // 디렉토리 구함
            $directories = $oProjectModel->getProjectDirectories(0);

            // 각각의 서브디렉토리 구함
            if(count($directories)) {
                foreach($directories as $key => $val) {
                    $directories[$key]->childs = $oProjectModel->getProjectDirectories($key);
                }
            }
            Context::set('directories', $directories);
			$oModuleModel =& getModel('module');
			$skin_info = $oModuleModel->loadSkinInfo($this->module_path, $project_config2->skin);
			if($skin_info->colorset)
			{
				Context::set('colorset', $skin_info->colorset);
			}
		}

        /**
         * @brief 프로젝트 기본 관리
         **/
        function dispProjectManage() {

            $oModuleModel = &getModel('module');
            $this->_getManageInfo();

            $this->setTemplateFile('project_setup');
        }

        /**
         * @brief 프로젝트 회원 그룹 관리
         **/
        function dispProjectMemberGroupManage() {
            // 멤버모델 객체 생성
            $oMemberModel = &getModel('member');

            // group_srl이 있으면 미리 체크하여 selected_group 세팅
            $group_srl = Context::get('group_srl');
            if($group_srl) {
                $selected_group = $oMemberModel->getGroup($group_srl);
                Context::set('selected_group',$selected_group);
            }

            // group 목록 가져오기
            $group_list = $oMemberModel->getGroups($this->site_srl);
            Context::set('group_list', $group_list);

            $this->setTemplateFile('group_list');
        }

        /**
         * @brief 프로젝트 모듈의 회원 관리
         **/
        function dispProjectMemberManage() {
            $oMemberModel = &getModel('member');

            // 회원 그룹을 구함
            $group_list = $oMemberModel->getGroups($this->site_srl);
            if(!$group_list) $group_list = array();
            Context::set('group_list', $group_list);

            $args->selected_group_srl = Context::get('selected_group_srl');
            if(!isset($group_list[$args->selected_group_srl])) {
                $args->selected_group_srl = implode(',',array_keys($group_list));
            }
            $search_target = trim(Context::get('search_target'));
            $search_keyword = trim(Context::get('search_keyword'));
            if($search_target && $search_keyword) {
                switch($search_target) {
                    case 'user_id' :
                            if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
                            $args->s_user_id = $search_keyword;
                        break;
                    case 'user_name' :
                            if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
                            $args->s_user_name = $search_keyword;
                        break;
                    case 'nick_name' :
                            if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
                            $args->s_nick_name = $search_keyword;
                        break;
                    case 'email_address' :
                            if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
                            $args->s_email_address = $search_keyword;
                        break;
                    case 'regdate' :
                            $args->s_regdate = ereg_replace("[^0-9]","",$search_keyword);
                        break;
                    case 'regdate_more' :
                            $args->s_regdate_more = substr(ereg_replace("[^0-9]","",$search_keyword) . '00000000000000',0,14);
                        break;
                    case 'regdate_less' :
                            $args->s_regdate_less = substr(ereg_replace("[^0-9]","",$search_keyword) . '00000000000000',0,14);
                        break;
                    case 'last_login' :
                            $args->s_last_login = $search_keyword;
                        break;
                    case 'last_login_more' :
                            $args->s_last_login_more = substr(ereg_replace("[^0-9]","",$search_keyword) . '00000000000000',0,14);
                        break;
                    case 'last_login_less' :
                            $args->s_last_login_less = substr(ereg_replace("[^0-9]","",$search_keyword) . '00000000000000',0,14);
                        break;
                    case 'extra_vars' :
                            $args->s_extra_vars = ereg_replace("[^0-9]","",$search_keyword);
                        break;
                }
            }

		    $query_id = 'member.getMemberListWithinGroup';
            $sort_order = Context::get('sort_order');
            if($sort_order != "asc") $sort_order = "desc";
            $args->sort_order = $sort_order;
            Context::set('sort_order', $sort_order);
		    $args->sort_index = "member.member_srl";
            $args->page = Context::get('page');
            $args->list_count = 40;
            $args->page_count = 10;
            $output = executeQuery($query_id, $args);

            $members = array();
            if(count($output->data)) {
                foreach($output->data as $key=>$val) {
                    $members[] = $val->member_srl;
                }
            }

            $members_groups = $oMemberModel->getMembersGroups($members, $this->site_srl);
            Context::set('members_groups',$members_groups);

            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('member_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('member_list');
        }

        /**
         * @brief 저장소 설정
         **/
        function dispProjectRepos() {
            $oProjectModel = &getModel('project');
            $oMemberModel = &getModel('member');

            // 저장소허용 여부
            $project_config = $oProjectModel->getConfig(0);
            Context::set('use_repos', $project_config->use_repos);
            Context::set('repos_url', $project_config->repos_url);

            // repository 정보 설정
            Context::set('repos_info', $oProjectModel->getProjectReposInfo($this->site_srl));

            // repository 커밋 허용 그룹 추출
            $args->site_srl = $this->site_srl;
            $output = executeQueryArray('project.getProjectReposGroup', $args);
            if($output->data && count($output->data)) foreach($output->data as $key => $val) $repos_group[] = $val->group_srl;
            else $repos_group = array();
            Context::set('repos_group', $repos_group);

            // 그룹 목록
            $group_list = $oMemberModel->getGroups($this->site_srl);
            Context::set('group_list', $group_list);

            $this->setTemplateFile('project_repos');
        }

        /**
         * @brief 접속 통계
         **/
        function dispProjectCounter() {
            // 정해진 일자가 없으면 오늘자로 설정
            $selected_date = Context::get('selected_date');
            if(!$selected_date) $selected_date = date("Ymd");
            Context::set('selected_date', $selected_date);

            // counter model 객체 생성
            $oCounterModel = &getModel('counter');

            // 전체 카운터 및 지정된 일자의 현황 가져오기
            $status = $oCounterModel->getStatus(array(0,$selected_date),$this->site_srl);
            Context::set('total_counter', $status[0]);
            Context::set('selected_day_counter', $status[$selected_date]);

            // 시간, 일, 월, 년도별로 데이터 가져오기
            $type = Context::get('type');
            if(!$type) {
                $type = 'day';
                Context::set('type',$type);
            }
            $detail_status = $oCounterModel->getHourlyStatus($type, $selected_date, $this->site_srl);
            Context::set('detail_status', $detail_status);

            // 표시
            $this->setTemplateFile('site_status');
        }

        /**
         * @brief 애드온/ 컴포넌트 설정
         **/
        function dispProjectComponent() {
            // 애드온 목록을 가져옴
            $oAddonModel = &getAdminModel('addon');
            $addon_list = $oAddonModel->getAddonList($this->site_srl);
            Context::set('addon_list', $addon_list);

            // 에디터 컴포넌트 목록을 가져옴
            $oEditorModel = &getModel('editor');
            Context::set('component_list', $oEditorModel->getComponentList(false, $this->site_srl));

            // 표시
            $this->setTemplateFile('components');
        }

        /**
         * @brief 내 저장소계정 설정
         **/
        function dispProjectAccountManage() {

            // 나의 프로젝트 목록 구함
            $logged_info = Context::get('logged_info');
            if(!$logged_info->member_srl) return new Object(-1,'msg_invalid_request');

            $args->member_srl = $logged_info->member_srl;

			// old
            $output = executeQueryArray('project.getMemberRepos', $args);
			if(!$output->data) $output->data = array();
			$my_project_list = $output->data;
            Context::set('dev_repos', $my_project_list);

			// new
			$my_projects = Context::get('my_projects');
			if($my_projects)
			{
				$site_srls = array();
				foreach($my_projects as $k => $v)
				{
					$site_srls[] = $v->site_srl;
				}

				$args->site_srl = implode(",", $site_srls);
				$output = executeQueryArray("project.getProjectMemberCount", $args);
				if(!$output->data) $output->data = array();

				$member_counts = array();
				foreach($output->data as $data)
				{
					$member_counts[$data->site_srl] = $data->count;
				}

				Context::set('member_counts',$member_counts);
			}

            Context::addJsFilter($this->module_path.'tpl/filter', 'update_account.xml');
            $this->setTemplateFile('account_manage');
        }

        /**
         * @brief rss
         **/
        function rss() {
            $oRss = &getView('rss');
            $oDocumentModel = &getModel('document');
            $oModuleModel = &getModel('module');

            $project_info = $oModuleModel->getModuleConfig('project');
            if($project_info->use_rss != 'Y') return new Object(-1,'msg_rss_is_disabled');

            $output = executeQueryArray('project.getRssList', $args);
            if($output->data) {
                foreach($output->data as $key => $val) {
                    unset($obj);
                    $obj = new DocumentItem(0);
                    $obj->setAttribute($val);
                    $document_list[] = $obj;
                }
            }

            $oRss->rss($document_list, $project_info->browser_title);
            $this->setTemplatePath($oRss->getTemplatePath());
            $this->setTemplateFile($oRss->getTemplateFile());
        }

		/**
		 * @brief project member
		 **/
		function dispProjectMember() {
			$oMemberModel = &getModel('member');
			$oModuleModel = &getModel('module');

            // 그룹 세팅
            Context::set('group_list', $oMemberModel->getGroups($this->site_srl));

            // 관리자를 구함
            $site_admins = $oModuleModel->getSiteAdmin($this->site_srl);
            if(count($site_admins)) {
				foreach($site_admins as $key => $val) {
						$site_admins[$key]->profile_image = $oMemberModel->getProfileImage($val->member_srl);
				}
            }
            Context::set('site_admins', $site_admins);

            // 개발 권한이 있는 회원을 구함
            $args->site_srl = $this->site_srl;
			$output = executeQueryArray('project.getProjectMembers', $args);
			if($output->data) {
				foreach($output->data as $key => $val) {
						$output->data[$key]->profile_image = $oMemberModel->getProfileImage($val->member_srl);
				}
			}

			Context::set('members', $output->data);
            $this->setTemplateFile('members');
		}

		function dispProjectContributors() {
			$oModel =& getModel('project');
			$page = Context::get('page');
			if(!$page) {
				$page = 1;
				Context::set('page', $page);
			}

			$sort_order = Context::get('sort_order');
			if(!$sort_order) {
				$sort_order = "points";
				Context::set('sort_order', $sort_order);
			}

			$search_keyword = Context::get('search_keyword');
			if($search_keyword)
			{
				$output = $oModel->getContributor($search_keyword);
			}
			else
			{
				$output = $oModel->getContributors($sort_order, $page, $search_keyword);
			}

			if($output->data)
			{
				$args->site_srls = implode(",", array_keys($output->site_srls));
				$output2 = executeQueryArray("project.getProjectsBySiteSrl", $args);
				$projects = array();
				foreach($output2->data as $data)
				{
					$projects[$data->site_srl] = $data;
				}
				Context::set('projects', $projects);
			}
			Context::set('contributors', $output->data);
			if($output->page_navigation)
				Context::set('page_navigation', $output->page_navigation);
			else
				Context::set('page_navigation', new PageHandler(0, 0, 1, 10));
            $this->setTemplateFile('contributors');
		}
    }
?>
