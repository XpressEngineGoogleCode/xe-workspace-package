<?php
    /**
     * @class  projectModel
     * @author NHN (developers@xpressengine.com)
     * @brief  project 모듈의  model class
     **/

    class projectModel extends project {

        var $site_module_info = null;
        var $site_srl = 0;

        function init() {
            // site_module_info값으로 프로젝트의 정보를 구함
            $this->site_module_info = Context::get('site_module_info');
            $this->site_srl = $this->site_module_info->site_srl;
        }

        function getMemberList($obj = NULL)
        {
            //get group_srl
            $args = NULL;
            $oMemberModel = &getModel('member');
            $group_list = $oMemberModel->getGroups($this->site_srl);
            if(!$group_list) $group_list = array();
            $args->selected_group_srl = implode(',',array_keys($group_list));

            //get member information
            $query_id = 'member.getMemberListWithinGroup';
            $sort_order = "desc";
		    $args->sort_index = "member.member_srl";
            $args->page = $obj->page ? $obj->page:1;
            $args->list_count = $obj->list_count ? $obj->list_count:40;
            $args->page_count = 10;
            $output = executeQuery($query_id, $args);
            return $output;
        }

		function getMemberCount($site_srl)
		{
			if(!$site_srl) return null;
            $c_args->site_srl = $site_srl;
            $output = executeQuery('project.getProjectMemberCount', $c_args);
			if(!$output->data || !$output->toBool()) return null;
			return $output->data->count;
		}

        function getConfig($site_srl = 0) {
            $oModuleModel = &getModel('module');

            if(!$site_srl) {
                $config = $oModuleModel->getModuleConfig('project');
                if(!$config) {
                    $config->project_main_mid = 'project';
                    $config->skin = 'xe_project';
                    $config->access_type = 'vid';
                    $config->default_domain = '';
                    $config->use_repos = N;
                } else {
                    if(!isset($config->project_main_mid)) $config->project_main_mid = 'project';
                    if(!isset($config->skin)) $config->skin = 'xe_project';
                    if(!isset($config->access_type)) $config->access_type = 'vid';
                    if($config->default_domain) {
                        if($config->default_domain && strpos($config->default_domain,':')!==false) $config->default_domain = preg_replace('/^(http|https):\/\//i','',$config->default_domain);
                        if($config->default_domain && substr($config->default_domain,-1)!='/') $config->default_domain .= '/';
                    }
                }
            } else {
                $config = $oModuleModel->getModulePartConfig('project', $site_srl);
            }

            return $config;
        }

		function _populateReleases($list, &$res)
		{
			$args->release_srls = implode(",", $list);
			$output2 = executeQueryArray("project.getReleases", $args);
            if($output2->data){
                foreach($output2->data as $doc)
                {
                    $res[$doc->release_srl] = $doc;
                }
            }
		}

		function _populateDocuments($list, &$res)
		{
			$args->order_type = "asc";
			$args->document_srls = implode(",", $list);
			$args->list_count = count($list);
			$output2 = executeQueryArray("document.getDocuments", $args);
			if(!$output2->data) $output2->data = array();
			foreach($output2->data as $doc)
			{
				if($doc->is_secret == "Y") continue;
				$res[$doc->document_srl] = $doc;
			}
			$args2->target_srl = $args->document_srls;
			$output = executeQueryArray("issuetracker.getIssues", $args2);
			if(!$output->data) return;
			foreach($output->data as $doc)
			{
				$res[$doc->document_srl] = $doc;
			}
		}

		function _populateComments($list, &$res)
		{
			$args->comment_srls = implode(",", $list);
            $output = executeQueryArray('project.getComments', $args);
			if(!$output->data) $output->data = array();
			foreach($output->data as $com)
			{
				if($com->is_secret == "Y") continue;
				$res[$com->comment_srl] = $com;
			}
		}

		function _populateAssigns($list, &$res)
		{
			$args->target_srl = implode(",", $list);
			//$output = executeQueryArray("project.getIssueHistories", $args);
			$output = executeQueryArray("issuetracker.getIssues", $args);
			if(!$output->data) return;
			foreach($output->data as $com)
			{
				$res[$com->target_srl] = $com;
			}
		}

		function _populateRevisions($list, &$res)
		{
            $oIssuetrackerModel =& getModel('issuetracker');
			$revisions = array();
			$oMemberModel =& getModel('member');
			foreach($list as $targets)
			{
				$target_arr = explode(".", $targets);
				$revisions[$target_arr[1]][] = $target_arr[0];
			}
			foreach($revisions as $site_srl => $revs)
			{
				$args = null;
				$args->revisions = implode(",", $revs);
				$args->site_srl = $site_srl;
				$output = executeQueryArray("project.getRevisions", $args);
				if(!$output->data) continue;
				foreach($output->data as $rev)
				{
                    $rev->message = $oIssuetrackerModel->_linkXE(htmlspecialchars($rev->message));
					if($rev->member_srl)
					{
						$member_info = $oMemberModel->getMemberInfoByMemberSrl($rev->member_srl);
						if($member_info)
						{
							$rev->author = $member_info->nick_name;
						}
					}
					$res[$rev->revision.".".$args->site_srl] = $rev;
				}
			}
		}

		function getNewItemsCounts($site_srl, $member_srl)
		{
			if(!$member_srl) return array();
			$args->member_srl = $member_srl;
			if($site_srl) $args->site_srl = $site_srl;
			$output = executeQueryArray("project.getNewItemsCounts", $args);
			if(!$output->data) return array();
			$res = array();
			foreach($output->data as $item)
			{
				$res[$item->type] = $item->count;
			}
			return $res;

		}

		function getNewItems($page, $site_srls = null, $member_srl = null, $type = null)
		{
			$args->member_srl = $member_srl;
			$args->page = $page;
			$args->order_type = "desc";
			if($site_srls != null)
			{
				$args->site_srls = $site_srls;
			}
			if(!$member_srl)
			{
				$args->remove_type = "a";
			}
            if($type)
            {
                $args->type = $type;
            }
			$output = executeQueryArray("project.getNewItems", $args);
			$sites = array();
			$modules = array();
			if($output->data)
			{
				$types = array();
				foreach($output->data as $item)
				{
					if($type && $item->type != $type) continue;
					if($item->type == "s") $types["s"][] = $item->target_srl.".".$item->site_srl;
					else if($item->type == "a") $types["d"][] = $item->target_srl;
					else $types[$item->type][] = $item->target_srl;
					$sites[$item->site_srl] = 1;
				}
				$res = array();
				if(count($types["d"]) > 0) $this->_populateDocuments($types["d"], $res);
				if(count($types["c"]) > 0) $this->_populateComments($types["c"], $res);
				if(count($types["D"]) > 0) $this->_populateReleases($types["D"], $res);
				if(count($types["s"]) > 0) $this->_populateRevisions($types["s"], $res);
				//if(count($types["a"]) > 0) $this->_populateAssigns($types["a"], $res);

				foreach($output->data as $key=>$item)
				{
					// check
					if($item->type == "s")
						$output->data[$key]->item = $res[$item->target_srl.".".$item->site_srl];
					else
						$output->data[$key]->item = $res[$item->target_srl];
					if(!$output->data[$key]->item) {
						unset($output->data[$key]);
						continue;
					}
					$module_srl = $res[$item->target_srl]->module_srl;
					if(in_array($item->type, array("d", "c"))) $modules[$module_srl] = 1;
				}

				if(count($modules) > 0)
				{
					$module_srls = array_keys($modules);
					$args->module_srls = implode(",",$module_srls);
					$output2 = executeQueryArray('module.getModulesInfo', $args);
                    if(count($output2->data))
                    {
                        foreach($output2->data as $module)
                        {
                            $modules[$module->module_srl] = $module->browser_title;
                        }
                    }
				}
			}
			$output->sites = array_keys($sites);
			$output->modules = $modules;
			return $output;
		}

		function getProjects($site_srls)
		{
			if(!$site_srls) return array();
			$args->site_srl = $site_srls;
			$output = executeQueryArray("project.getProjectsInfo", $args);
			$res = array();
			if($output->data)
			{
				foreach($output->data as $project)
				{
					$res[$project->site_srl] = $project;
				}
			}
			return $res;
		}

		function getProjectImages($site_srl)
		{
		    $siteInfo = NULL;

		    $logo_src = 'files/attach/project_logo/'.$site_srl.'.jpg';
            if(file_exists(_XE_PATH_.$logo_src)) $siteInfo->project_logo = $logo_src.'?rnd='.filemtime(_XE_PATH_.$logo_src);
            else $siteInfo->project_logo = $this->module_path.'tpl/img/@proj_fallback.png';

            $banner_src = 'files/attach/project_banner/'.$site_srl.'.jpg';
            if(file_exists(_XE_PATH_.$banner_src)) $siteInfo->project_banner = $banner_src.'?rnd='.filemtime(_XE_PATH_.$banner_src);
            else $siteInfo->project_banner = $this->module_path.'tpl/img/projectBanner.gif';
            return $siteInfo;
		}

        function getProjectInfo($site_srl) {
            $args->site_srl = $site_srl;
            $output = executeQuery('project.getProjectInfo', $args);
            if(!$output->toBool() || !$output->data) return;

            $logo_src = 'files/attach/project_logo/'.$site_srl.'.jpg';
            if(file_exists(_XE_PATH_.$logo_src)) $output->data->project_logo = $logo_src.'?rnd='.filemtime(_XE_PATH_.$logo_src);
            else $output->data->project_logo = $this->module_path.'tpl/img/@proj_fallback.png';

            $banner_src = 'files/attach/project_banner/'.$site_srl.'.jpg';
            if(file_exists(_XE_PATH_.$banner_src)) $output->data->project_banner = $banner_src.'?rnd='.filemtime(_XE_PATH_.$banner_src);
            else $output->data->project_banner = $this->module_path.'tpl/img/projectBanner.gif';

            $info = $output->data;
            $url = getSiteUrl($info->domain,'');
            if(substr($url,0,1)=='/') $url = substr(Context::getRequestUri(),0,-1).$url;
            $info->url = $url;

            $output = executeQueryArray('project.getProjectDirMap', $args);
            if($output->data) {
                foreach($output->data as $key => $val) {
                    $info->directory[$val->parent_directory_srl] = $val;
                }
            } else {
                $info->directory = array();
            }
            return $info;
        }

        function getProjectAllDirectories() {
            $output = executeQueryArray('project.getProjectDirectoryModuleCount');
            if($output->data) foreach($output->data as $key => $val) $counts[$val->project_dir_srl] = $val->count;
            else $counts = array();

            $output = executeQueryArray('project.getProjectDirectories', $args);
            if(!$output->data) return array();
            foreach($output->data as $key => $val) {
                if($val->parent_directory_srl) {
                    $val->count = (int)$counts[$val->project_dir_srl];
                    $list[$val->parent_directory_srl]->childs[$val->project_dir_srl] = $val;
                    $list[$val->parent_directory_srl]->count += $val->count;
                } else {
                    $list[$val->project_dir_srl] = $val;
                }
            }

            return $list;
        }

        function getProjectDirectories($parent_directory_srl = 0) {
            $args->parent_directory_srl = $parent_directory_srl;
            $output = executeQueryArray('project.getProjectDirectories', $args);
            if(!$output->toBool() || !$output->data) return array();
            foreach($output->data as $key => $val) {
                $list[$val->project_dir_srl] = $val;
                $parent_directory_srl_list[] = $val->project_dir_srl;
            }

            if(count($parent_directory_srl_list)) {
                $sargs->parent_directory_srl = implode(',',$parent_directory_srl_list);
                $output = executeQueryArray('project.getProjectDirectoryCount', $sargs);
                if($output->data) {
                    foreach($output->data as $key => $val) $list[$val->parent_directory_srl]->count = $val->count;
                }
            }

            return $list;
        }

        function getProjectReposInfo($site_srl) {
            $args->site_srl = $site_srl;
            $output = executeQuery('project.getProjectReposID', $args);
            return $output->data;
        }

        function getProjectData() {
            $output = executeQueryArray('project.getReposMembers');
            if(!$output->toBool() || !$output->data) return;

            $projects = array();
            foreach($output->data as $key => $val) {
                if(!isset($projects[$val->repos_id]->title)) $projects[$val->repos_id]->title = $val->title;
                $projects[$val->repos_id]->members[$val->user_id] = $val->password;
            }
            return $projects;
        }

		function getProjectListInSummary($page, $sort_index='point', $list_count = 5, $search_keyword = null, $project_dir_srl = null) {
            $args->page = $page;
            $args->list_count = $list_count;
            $args->page_count = 10;
			$args->sort_index = $sort_index;
			$args->order_type = "desc";
			$args->search_keyword = $search_keyword;
			if($args->sort_index != "member_count")
			{
				if($project_dir_srl)
				{
					$args->project_dir_srl = $project_dir_srl;
					$output = executeQueryArray("project.getDirProjectListInSummary", $args);
				}
				else
				{
					$output = executeQueryArray('project.getProjectListInSummary', $args);
				}
                //debugPrint($output);
				if(!$output->data) return $output;
				$site_srls = array();
				foreach($output->data as $data)
				{
					$site_srls[] = $data->site_srl;
				}
				$args->site_srl = implode(",", $site_srls);
				$output2 = executeQueryArray("project.getProjectMemberCount", $args);
				$member_counts = array();
				foreach($output2->data as $data)
				{
					$member_counts[$data->site_srl] = $data->count;
				}
				foreach($output->data as $key=>$data)
				{
					$output->data[$key]->member_count = $member_counts[$data->site_srl]?$member_counts[$data->site_srl]:0;
				}
			}
			else
			{
				if($project_dir_srl)
				{
					$args->project_dir_srl = $project_dir_srl;
					$output = executeQueryArray('project.getDirProjectMemberCountList', $args);
				}
				else
				{
					$output = executeQueryArray('project.getProjectMemberCountList', $args);
				}
				if(!$output->data) return $output;
				$site_srls = array();
				foreach($output->data as $data)
				{
					$site_srls[] = $data->site_srl;
				}
				$args->site_srl = implode(",", $site_srls);
				$output2 = executeQueryArray("project.getProjectPoints", $args);
				$points = array();
				foreach($output2->data as $data)
				{
					$points[$data->site_srl] = $data->point;
				}
				foreach($output->data as $key=>$data)
				{
					$output->data[$key]->point = $points[$data->site_srl]?$points[$data->site_srl]:0;
				}
			}
			return $output;
		}

        function getProjectList($page, $search_keyword=null, $project_dir_srl = 0, $list_count = 20, $sort_index='list_order', $member_srl = null ) {
            if(!$page) $page = 1;
            $args->page = $page;
            $args->search_keyword = $search_keyword;
            $args->list_count = $list_count;
            $args->order_type = 'asc';
			if($sort_index == 'rank') $args->sort_index = 'rank';
			else $args->sort_index = 'list_order';
            if($project_dir_srl) {
                $args->project_dir_srl = $project_dir_srl;
                $output = executeQueryArray('project.getDirectoryProjectList', $args);
            } else {
				if($member_srl)
				{
					$args->member_srl = $member_srl;
					$output = executeQueryArray('project.getMyProjectList', $args);
				}
				else
				{
					$output = executeQueryArray('project.getProjectList', $args);
				}
            }
            return $output;
        }

		function getIssueMilestones($startdate, $enddate, $sort_order, $list_count, &$more, $site_srls = null)
		{
			$args->startdate = $startdate;
			$args->enddate = $enddate;
			$args->sort_order = $sort_order;
			$args->list_count = $list_count+1;
			$args->site_srls = $site_srls;
			$output = executeQueryArray("project.getProjectPlanDeadlines", $args);
			if(!$output->data) return array();
			if(count($output->data) > $list_count)
			{
				$more = true;
				array_pop($output->data);
			}
			$item = array_shift($output->data);
			$args->startdate = $item->deadline;
			if(count($output->data))
			{
				$item = array_pop($output->data);
			}
			$args->enddate = $item->deadline;
			if($args->enddate < $args->startdate)
			{
				$args->enddate = $args->startdate;
				$args->startdate = $item->deadline;
			}
			$output = executeQueryArray("project.getProjectPlans", $args);
			return $output->data;
		}

		function getContributor($search_keyword)
		{
			$args->search_keyword = $search_keyword;
			$output = executeQueryArray("project.getContributor", $args);
			if(!$output->data) return array();
			$contributors = array();
			foreach($output->data as $data)
			{
				if($contributors[$data->member_srl][$data->type])
				{
					$contributors[$data->member_srl][$data->type] += $data->cnt;
				}
				else
				{
					$contributors[$data->member_srl][$data->type] = $data->cnt;
				}
				$contributors[$data->member_srl]["site_srls"][$data->site_srl] = 1;
				$contributors[$data->member_srl]["point"] = $contributors[$data->member_srl]["point"] + $data->cnt * $data->point;
				$output->site_srls[$data->site_srl] = 1;
			}
			$output->data = array(array_shift($output->data));

			foreach($output->data as $key=>$data)
			{
				foreach($contributors[$data->member_srl] as $key2 => $val)
				{
					$output->data[$key]->{$key2} = $val;
				}
			}

			return $output;
		}

		function getMyProject()
        {
            if(!Context::get('is_logged')) {
                return;
            }
			$logged_info = Context::get('logged_info');
			$margs->member_srl = $logged_info->member_srl;
			$output = executeQueryArray('project.getMyProjects', $margs);
			if(!$output->data) $output->data = array();
			$this->my_projects = $output->data;
			$module_info = $this->module_info;
            $module_path = './modules/'.$module_info->module.'/';
			$skin_path = $module_path.'skins/'.$module_info->skin.'/';
			if(!$module_info->skin || !is_dir($skin_path)) {
				$skin_path = $module_path.'skins/xe_pacakgePro/';
			}
			Context::set('project',$output->data);

			$oTemplateHandler = &TemplateHandler::getInstance();
            $result = new Object();
            $result->add('html', $oTemplateHandler->compile($skin_path, 'myproject.html'));
            $this->add('html', $result->get('html'));
        }

		function getContributors($sort_order = "points", $page = 1)
		{
			$args->page = $page;
			if($sort_order == "points")
			{
				$output = executeQueryArray("project.getContributorByPoints", $args);
				if(!$output->data) return $output;
			}
			else
			{
				switch($sort_order) {
					case "articles":
						$args->type = "d";
						break;
					case "comments":
						$args->type = "c";
						break;
					default:
						$args->type = "s";
				}

				$output = executeQueryArray("project.getContributorByArticles", $args);
				if(!$output->data) return $output;
			}

			$member_srls = array();
			foreach($output->data as $data)
			{
				$member_srls[] = $data->member_srl;
			}
			$args->member_srls = implode(",", $member_srls);
			$output2 = executeQueryArray("project.getContributors", $args);
			$contributors = array();
			foreach($output2->data as $data)
			{
				if($contributors[$data->member_srl][$data->type])
				{
					$contributors[$data->member_srl][$data->type] += $data->cnt;
				}
				else
				{
					$contributors[$data->member_srl][$data->type] = $data->cnt;
				}
				$contributors[$data->member_srl]["site_srls"][$data->site_srl] = 1;
				$contributors[$data->member_srl]["point"] = $contributors[$data->member_srl]["point"] + $data->cnt * $data->point;
				$output->site_srls[$data->site_srl] = 1;
			}
			foreach($output->data as $key=>$data)
			{
				foreach($contributors[$data->member_srl] as $key2 => $val)
				{
					$output->data[$key]->{$key2} = $val;
				}
			}
			return $output;
		}

		function getReleases($sort_order, $page)
		{
			if($sort_order)
			{
			    $this->_getSearchOrder($sort_order, $args);
			}
			$args->page = $page;
			$output = executeQueryArray("project.getReleaseList", $args);
			return $output;
		}

		function _getSearchOrder($sort_order, &$args)
		{
		        $args->sort_index = $sort_order;
			if($args->sort_index == "oldregdate")
			{
				$args->sort_index = "issue_releases.regdate";
				$args->sort_order = "asc";
			}
			else
			{
				$args->sort_order = "desc";
			}
		 }

		function getReleaseList($sort_index='oldregdate', $page=1, $dir=array(), $keyword='' )
		{
		    $args = NULL;
		    if($searchData->sort_index)
		    {
		        $this->_getSearchOrder($sort_order, $args);
		    }
		    $args->page = intval($page);
		    if($keyword)
		    {
		        $args->search_keyword = $keyword;
		    }
		    if($dir)
		    {
		        $args->dir = $dir;
		    }

                      if($dir)
                      {
                          $output = executeQueryArray("project.getReleaseList2", $args);
                      }else
                      {
                          $output = executeQueryArray("project.getReleaseList", $args);
                      }

		    return $output;
		}
    }