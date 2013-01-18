<?php
    /**
     * @class  issuetrackerView
     * @author NHN (developers@xpressengine.com)
     * @brief  issuetracker 모듈의 View class
     **/


    class issuetrackerView extends issuetracker {

        /**
         * @brief 초기화
         *
         * issuetracker 모듈은 일반 사용과 관리자용으로 나누어진다.\n
         **/
        function init() {
            //get module_info
            $this->_getModuleInfo();

            /**
             * 스킨등에서 사용될 module_srl이나 module_info등을 context set
             **/
            // 템플릿에서 사용할 변수를 Context::set()
            if($this->module_srl) Context::set('module_srl',$this->module_srl);
            if(!$this->module_info->svn_cmd) $this->module_info->svn_cmd = '/usr/bin/svn';

            // 현재 호출된 게시판의 모듈 정보를 module_info 라는 이름으로 context setting
            Context::set('module_info',$this->module_info);

            /**
             * 스킨 경로를 미리 template_path 라는 변수로 설정함
             **/
            $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);

            // 만약 스킨 경로가 없다면 xe_issuetracker로 변경
            if(!$this->module_info->skin || !is_dir($template_path)) {
                $this->module_info->skin = 'xe_issuetracker';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }

            if($this->module_info->skin == 'xe_package')
            {
                $this->list_count = 10;
            }

            $this->setTemplatePath($template_path);

            if(!$this->grant->ticket_view) unset($GLOBALS['lang']->project_menus['dispIssuetrackerViewIssue']);
            if(!$this->grant->ticket_write) unset($GLOBALS['lang']->project_menus['dispIssuetrackerNewIssue']);
            if(!$this->grant->timeline) unset($GLOBALS['lang']->project_menus['dispIssuetrackerTimeline']);
            if(!$this->grant->browser_source) unset($GLOBALS['lang']->project_menus['dispIssuetrackerViewSource']);
            if(!$this->grant->download) unset($GLOBALS['lang']->project_menus['dispIssuetrackerDownload']);
            if(!$this->grant->manager) unset($GLOBALS['lang']->project_menus['dispIssuetrackerAdminProjectSetting']);

            // 템플릿에서 사용할 검색옵션 세팅 (검색옵션 key값은 미리 선언되어 있는데 이에 대한 언어별 변경을 함)
            foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);

            $oDocumentModel = &getModel('document');
            $extra_keys = $oDocumentModel->getExtraKeys($this->module_srl);
            Context::set('extra_keys', $extra_keys);

            // 모듈정보를 확인하여 확장변수에서도 검색이 설정되어 있는지 확인
            if(count(Context::get('extra_keys'))) {
                foreach(Context::get('extra_keys') as $key => $val) {
                    if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
                }
            }
            Context::set('search_option', $search_option);

            $oModuleModel = &getModel('module');
            $module_config = $oModuleModel->getModulePartConfig('issuetracker',$this->module_srl);
            if($module_config) $this->default_enable = $module_config->display_option;

            // 템플릿에서 사용할 노출옵션 세팅
            foreach($this->display_option as $opt) {
                $obj = null;
                $obj->title = Context::getLang($opt);
                $checked = Context::get('d_'.$opt);
                if($opt == 'title' || $checked==1 || (Context::get('d')!=1&&in_array($opt,$this->default_enable))) $obj->checked = true;
                $display_option[$opt] = $obj;
            }
            Context::set('display_option', $display_option);

            if(!Context::get('act')) {
                if (!Context::get('document_srl')) {
                    $this->act = 'dispIssuetrackerViewMilestone';
                    Context::set('act','dispIssuetrackerViewMilestone');
                } else {
                    $this->act = 'dispIssuetrackerViewIssue';
                    Context::set('act','dispIssuetrackerViewIssue');
                }
            }

            // javascript, JS 필터 추가
            Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
            Context::addJsFile($this->module_path.'tpl/js/issuetracker.js');

            //if the skin is xe_package then the milestone is called goal
            if($this->module_info->skin == 'xe_package')
            {
                global $lang;
                $lang->milestone = 'Goal';
                $lang->download = 'Release';
                $lang->package = 'Product';
            }
        }

        function _getModuleInfo()
        {
            //if has site_srl ,then get module info from site_srl
            if($this->module_info->site_srl)
            {
                $module = &getModel('module');
                $this->module_info = $module->getModuleInfoByMid('issuetracker', $this->module_info->site_srl);
            }
        }

        function dispIssuetrackerTimeline() {
            if(!$this->grant->timeline) return $this->dispIssuetrackerMessage('msg_not_permitted');
            $oController = &getController('issuetracker');
            $oController->syncChangeset($this->module_info);
            $oModel = &getModel('issuetracker');
			Context::set('issue_count', $oModel->getIssueCount($this->module_srl));
			Context::set('changeset_count', $oModel->getChangesetCount($this->module_srl));

            $this->setTemplateFile('timeline');

			$search_value = Context::get('search_value');
			$oldestIssue = $oModel->getOldestIssue($this->module_srl);
			$oldestChange = $oModel->getOldestChange($this->module_srl);
			if(!$oldestIssue)
			{
				Context::set('oldestItem', $oldestChange);
			}
			else if(!$oldestChange)
			{
				Context::set('oldestItem', $oldestIssue);
			}
			else if($oldestChange > $oldestIssue)
			{
				Context::set('oldestItem', $oldestIssue);
			}
			else
			{
				Context::set('oldestItem', $oldestChange);
			}
            $targets = Context::get('targets');
            if(!$targets || !is_array($targets) || !count($targets))
            {
                $targets = array('issue_created', 'issue_changed', 'commit');
                Context::set('targets', $targets);
            }
            $res = $oModel->getChangesets($this->module_info->module_srl, Context::get('startdate'), Context::get('enddate'), 20, $targets, $search_value);
			$changesets = $res->data;
            Context::set('changesets', $changesets);
			if($res->lastdate) Context::set('lastdate', $res->lastdate);
            $issues = array();
			$target_srls = array();
            foreach($changesets as $changeset)
            {
                if(!$changeset->target_srl) continue;
				$target_srls[] = $changeset->target_srl;
            }
			$issues = $oModel->populateIssues($target_srls);

            Context::set('issues', $issues);
        }

        function dispIssuetrackerOneMilestone()
        {
            // 접근 권한 체크
            if(!$this->grant->access) return $this->dispIssuetrackerMessage('msg_not_permitted');

            $oIssuetrackerModel = &getModel('issuetracker');

            $mSrl = Context::get('milestone_srl');
            if(!$mSrl) return $this->dispIssuetrackerMessage('msg_invalid_request');
            $output = $oIssuetrackerModel->getMilestone($mSrl);
            $milestone = $output->data;

            //if is xe_package skin then add project information to the Context
            if($this->module_info->skin == 'xe_package')
            {
                $oIssuetrackerModel = &getModel('issuetracker');
                Context::set('project', $oIssuetrackerModel->getProjectInfo($this->module_info->module_srl));
            }

            if(!$milestone->is_completed)$milestone->is_completed = "N";

            if($milestone) {
				$status_list = array("new", "reviewing", "assign", "resolve", "reopen", "invalid", "duplicated", "postponed");
					$issues = $oIssuetrackerModel->getIssueCountByStatus($milestone->milestone_srl, $this->module_srl);
					foreach($status_list as $status)
					{
						if(!$issues[$status]) $issues[$status] = 0;
					}

                    $milestone->issues = $issues;
                    $milestones[$milestone->milestone_srl] = $milestone;
            }
            Context::set('milestone',$milestone);

            // 프로젝트 메인 페이지 출력
            $this->setTemplateFile('oneMilestone');
        }

        /**
         * @brief 마일스톤과 그에 따른 통계 제공
         **/
        function dispIssuetrackerViewMilestone() {
            // 접근 권한 체크
            if(!$this->grant->access) return $this->dispIssuetrackerMessage('msg_not_permitted');

            $oIssuetrackerModel = &getModel('issuetracker');

            //if is xe_package skin then add paging
            $paged = NULL;
            if($this->module_info->skin == 'xe_package')
            {
                $paged = Context::get('page') ? Context::get('page') : 1;
                $order_type = Context::get('order_type') ? Context::get('order_type'):'desc';

                Context::set('order_type', $order_type);
            }
            $output = $oIssuetrackerModel->getList($this->module_info->module_srl, 'Milestones', $paged, $order_type);

            //if is xe_package skin then add project information to the Context
            if($this->module_info->skin == 'xe_package')
            {
                $oIssuetrackerModel = &getModel('issuetracker');
                Context::set('project', $oIssuetrackerModel->getProjectInfo($this->module_info->module_srl));
            }

            if($paged)
            {
                Context::set('total_count', $output->total_count);
                Context::set('total_page', $output->total_page);
                Context::set('page', $output->page);
                Context::set('page_navigation', $output->page_navigation);
                $output = $output->data ? $output->data:array();
            }
            $milestones = array();
            $notassigned = null;
            $notassigned->milestone_srl = 0;
            $notassigned->is_completed = "N";
            array_unshift($output, $notassigned);

            if($output) {
				$status_list = array("new", "reviewing", "assign", "resolve", "reopen", "invalid", "duplicated", "postponed");
                foreach($output as $key => $milestone) {
					$issues = $oIssuetrackerModel->getIssueCountByStatus($milestone->milestone_srl, $this->module_srl);
					foreach($status_list as $status)
					{
						if(!$issues[$status]) $issues[$status] = 0;
					}

                    $milestone->issues = $issues;
                    $milestones[$milestone->milestone_srl] = $milestone;

                }
            }
            Context::set('milestones',$milestones);

            // 프로젝트 메인 페이지 출력
            $this->setTemplateFile('milestone');
        }

        /**
         * @brief 소스 브라우징
         **/
        function dispIssuetrackerViewSource() {
            // 접근 권한 체크
            if(!$this->grant->browser_source) return $this->dispIssuetrackerMessage('msg_not_permitted');
			if(!$this->module_info->svn_url) return $this->dispIssuetrackerMessage('not_using_repository');
            require_once($this->module_path.'classes/svn.class.php');

            $path = urldecode(Context::get('path'));
            if(!$path) $path = '/';
            Context::set('svn_url', $this->module_info->svn_url);
            Context::set('path', $path);

            $revs = Context::get('revs');
            $erev = Context::get('erev');
            $brev = Context::get('brev');

            $oSvn = new Svn($this->module_info->svn_url, $this->module_info->svn_cmd, $this->module_info->svn_userid, $this->module_info->svn_passwd);
            $current = $oSvn->getStatus($path);
            Context::set('current', $current);

            $type = Context::get('type');
            switch($type) {
                case 'diff' :
                case 'compare' :
                        $comp = $oSvn->getComp($path, $brev, $erev);
                        Context::set('comp', $comp);

                        $path_tree = Svn::explodePath($path, true);
                        Context::set('path_tree', $path_tree);

                        $this->setTemplateFile('source_compare');
                    break;
                case 'log' :
                        if(!$erev) $erev = $current->revision;
                        $logs = $oSvn->getLog($path, $erev, $brev, false, 21);
						if(count($logs) > 20)
						{
							$lastrev = array_pop($logs);
							Context::set('lastrev', $lastrev->revision);
						}

                        Context::set('logs', $logs);

                        if(!$erev) $erev = $current->erev;
                        context::set('erev', $erev);
                        context::set('brev', $brev);

                        $path_tree = Svn::explodePath($path, true);
                        Context::set('path_tree', $path_tree);

                        $this->setTemplateFile('source_log');
                    break;
                case 'file' :
                        if($revs) $erev = $revs;
                        if(!$erev) $erev = $current->revision;
                        $content = $oSvn->getFileContent($path, $erev);
                        Context::set('content', $content);

                        $logs = $oSvn->getLog($path, $erev, $brev, false, 2);
                        $erev = $logs[0]->revision;
                        $brev = $logs[1]->revision;
                        context::set('erev', $erev);
                        context::set('brev', $brev);

                        $path_tree = Svn::explodePath($path, true);
                        Context::set('path_tree', $path_tree);

                        $file_name = array_pop(array_keys($path_tree));
                        $file_ext = array_pop(explode(".",$file_name));
                        $extlist = array(
                            "document" => array("doc", "pdf", "hwp"),
                            "image" => array("jpg", "jpeg", "jpe", "gif", "png", "bmp"),
                            "sound" => array("mp3", "ogg", "wma", "wav"),
                            "movie" => array("avi", "mpg", "mpeg", "mpe", "wmv", "asf", "asx", "mov", "flv", "swf")
                        );

						foreach($extlist as $key => $exts) {
							foreach($exts as $s_key => $ext) {
                                if(!strcasecmp($file_ext, $ext)) {
                                    $file_type = $key;
                                    break 2;
                                }
                            }
						}

                        if(!$file_type)
                        {
                            $file_type = "code";
                            $extToLang = array(
                                "h" => "Cpp",
                                "cpp" => "Cpp",
                                "csharp" => "CSharp",
                                "css" => "Css",
                                "html" => "Xml",
                                "sql" => "Sql",
                                "java" => "Java",
                                "py" => "Python",
                                "rb" => "Ruby",
                                "js" => "JScript",
                                "c" => "Cpp",
                                "vb" => "Vb",
                                "xml" => "Xml",
                                "php" => "Php"
                            );

                            $file_ext_tmp = strtolower($file_ext);
                            if($extToLang[$file_ext_tmp])
                            {
                                $file_ext = $extToLang[$file_ext_tmp];
                            }
                        }
                        Context::set('file_type', $file_type);
                        Context::set('file_ext', $file_ext);
                        Context::set('template_path', sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin));

                        $this->setTemplateFile('source_file_view');
                    break;


                default :
                        $path_tree = Svn::explodePath($path, false);
                        Context::set('path_tree', $path_tree);
                        $list = $oSvn->getList($path, $revs);
                        Context::set('list', $list);
                        $text_list = array("sql","c","cpp","h","hpp","vb","java","rb","py","xml","html","php","csharp","txt","text","css","js");
                        foreach($list as $key => $item)
                        {
                            if($item->type == "dir") continue;
                            $ext = array_pop(explode(".",$item->name));
                            if(in_array($ext, $text_list))
                            {
                                $item->ext_type = "text";
                            }
                            else
                            {
                                $item->ext_type = "bin";
                            }
                            $list[$key] = $item;
                        }
                        $this->setTemplateFile('source_list');
                    break;
            }

            Context::addJsFile($this->module_path.'tpl/js/svn.js');
        }

        /**
         * @brief 이슈 목록 및 내용 보기
         **/
        function dispIssuetrackerViewIssue() {
            // 접근 권한 체크
            if(!$this->grant->ticket_view) return $this->dispIssuetrackerMessage('msg_not_permitted');

            // 프로젝트 관련 정보를 미리 구해서 project 라는 변수로 context setting
            $oIssuetrackerModel = &getModel('issuetracker');
            Context::set('project', $oIssuetrackerModel->getProjectInfo($this->module_info->module_srl));

            // 선택된 이슈가 있는지 조사하여 있으면 context setting
            $document_srl = Context::get('document_srl');
            $oIssue = $oIssuetrackerModel->getIssue(0);

            if($document_srl) {
                $oIssue->setIssue($document_srl);

                if(!$oIssue->isExists()) {
                    unset($document_srl);
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_founded');
                } else {
                    if($oIssue->get('module_srl')!=Context::get('module_srl') ) return $this->stop('msg_invalid_request');
                    if($this->grant->manager) $oIssue->setGrant();
                    if(!$this->grant->ticket_view && !$oIssue->isGranted()) {
                        $oIssue = null;
                        $oIssue = $oIssuetrackerModel->getIssue(0);

                        Context::set('document_srl','',true);

                        $this->alertMessage('msg_not_permitted');
                    } else {
                        // 브라우저 타이틀에 글의 제목을 추가
                        Context::addBrowserTitle($oIssue->getTitleText());
                    }
                }
            }

            // issue가 존재하지 않으면 목록 출력을 위한 준비
            if(!$oIssue->isExists()) {

                $args->module_srl = $this->module_srl;

                // 목록을 구하기 위한 대상 모듈/ 페이지 수/ 목록 수/ 페이지 목록 수에 대한 옵션 설정
                $args->page = Context::get('page');
                $args->list_count = 20;
                $args->page_count = 10;

                // issue 검색을 위한 변수
                $args->milestone_srl = Context::get('milestone_srl');
                $args->priority_srl = Context::get('priority_srl');
                $args->type_srl = Context::get('type_srl');
                $args->component_srl = Context::get('component_srl');
                $args->status = Context::get('status');
                $args->occured_version_srl = Context::get('release_srl');
                $args->resolution_srl = Context::get('resolution_srl');
                $args->assignee_srl = Context::get('assignee_srl');
                $args->member_srl = Context::get('member_srl');

                // status 점검
                if(!is_array($args->status)||!count($args->status)) {
                    $args->status = array('new','assign','reopen','reviewing');
                    Context::set('status',$args->status);
                }
                $args->status = "'".implode("','",$args->status)."'";

                // 키워드 검색을 위한 변수
                $args->search_target = Context::get('search_target'); ///< 검색 대상 (title, contents...)
                $args->search_keyword = Context::get('search_keyword'); ///< 검색어

                //if no search_target but has search_keyword, then search all search_option
                if(empty($args->search_target) && $args->search_keyword)
                {
                    $args->search_target = $this->search_option;
                }

                // 일반 글을 구해서 context set
                $output = $oIssuetrackerModel->getIssueList($args);
                Context::set('issue_list', $output->data);
                Context::set('total_count', $output->total_count);
                Context::set('total_page', $output->total_page);
                Context::set('page', $output->page);
                Context::set('page_navigation', $output->page_navigation);

                // 스킨에서 사용하기 위해 context set
                Context::set('oIssue', $oIssue);

                $this->setTemplateFile('issue_list');
            } else {
                // 히스토리를 가져옴
                $histories = $oIssuetrackerModel->getHistories($oIssue);
                $oIssue->add('histories', $histories);

                if($oIssue->isAccessible()) $oIssue->updateReadedCount();

                // 스킨에서 사용하기 위해 context set
                Context::set('oIssue', $oIssue);

                // javascript 필터 추가
                Context::addJsFilter($this->module_path.'tpl/filter', 'insert_history.xml');

                $this->setTemplateFile('view_issue');
            }

            // 커미터 목록을 추출
            Context::set('commiters', $oIssuetrackerModel->getGroupMembers($this->module_info->module_srl,'commiter'));
        }

        /**
         * @brief Display a form to write an issue
         */
        function dispIssuetrackerNewIssue()
        {
            if(!$this->grant->ticket_write) return $this->dispIssuetrackerMessage('msg_not_permitted');

            $oIssuetrackerModel = &getModel('issuetracker');
            $project = $oIssuetrackerModel->getProjectInfo($this->module_info->module_srl);
            Context::set('project', $project);

            $document_srl = Context::get('document_srl');

            $oIssue = $oIssuetrackerModel->getIssue(0, $this->grant->manager);
            $oIssue->setIssue($document_srl);

            if(!$oIssue->isExists()) {
                $document_srl = getNextSequence();
                // 신규 생성시에는 연결된 module이 없으므로 module_srl을 설정해준다.
                $oIssue->setModuleSrl($this->module_info->module_srl);
                Context::set('document_srl',$document_srl);
            }

            // 글을 수정하려고 할 경우 권한이 없는 경우 비밀번호 입력화면으로
            if($oIssue->isExists() && !$oIssue->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('document_srl',$document_srl);

            // 확장변수처리를 위해 xml_js_filter를 직접 header에 적용
            $oDocumentController = &getController('document');
            $oDocumentController->addXmlJsFilter($this->module_info->module_srl);
            if($oIssue->isExists()) Context::set('extra_keys', $oIssue->getExtraVars());

            // javascript 필터 추가
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');

            $this->setTemplateFile('newissue');
            Context::set('oIssue', $oIssue);

            // 커미터 목록을 추출
            Context::set('commiters', $oIssuetrackerModel->getGroupMembers($this->module_info->module_srl,'commiter'));
        }

        function dispIssuetrackerDeleteIssue() {
            if(!$this->grant->ticket_write) return $this->dispIssuetrackerMessage('msg_not_permitted');

            $document_srl = Context::get('document_srl');
            if(!$document_srl) return $this->dispIssuetrackerMessage('msg_invalid_request');

            $oIssuetrackerModel = &getModel('issuetracker');
            $oIssue = $oIssuetrackerModel->getIssue(0);

            $oIssue->setIssue($document_srl);

            if(!$oIssue->isExists()) return $this->dispIssuetrackerMessage('msg_invalid_request');
            if($oIssue->get('module_srl')!=Context::get('module_srl') ) return $this->dispIssuetrackerMessage('msg_invalid_request');

            if($this->grant->manager) $oIssue->setGrant();

            if(!$oIssue->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oIssue', $oIssue);

            // javascript 필터 추가
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_issue.xml');

            $this->setTemplateFile('delete_form');
        }

        function dispIssuetrackerDownload() {
            // 접근 권한 체크
            if(!$this->grant->download) return $this->dispIssuetrackerMessage('msg_not_permitted');
            $package_srl = Context::get('package_srl');
            $release_srl = Context::get('release_srl');
            $page = Context::get('page') ? Context::get('page'):1;
            $isPage = TRUE;
            $list_count = $this->list_count ? $this->list_count:1000;

            $oIssuetrackerModel = &getModel('issuetracker');

            if($release_srl) {
                $release = $oIssuetrackerModel->getRelease($release_srl);
                if(!$release) return $this->dispIssuetrackerMessage("msg_invalid_request");
                Context::set('release', $release);

                $package_srl = $release->package_srl;
                $package_list = $oIssuetrackerModel->getPackageList($this->module_srl, $package_srl, -1,$isPage,$page);
                if($isPage)
                {
                    $page_navigation = $package_list->page_navigation;
                    $package_list = $package_list->data;
                }
                unset($package_list[$release->package_srl]->releases);
                $package_list[$release->package_srl]->releases[$release->release_srl] = $release;
            } else {
                if(!$package_srl) {
                    $package_list = $oIssuetrackerModel->getPackageList($this->module_srl, 0, 0,$isPage,$page);
                } else {
                    $package_list = $oIssuetrackerModel->getPackageList($this->module_srl, $package_srl, 0,$isPage,$page);
                }
                if($isPage)
                {
                    $page_navigation = $package_list->page_navigation;
                    $package_list = $package_list->data;
                }
            }

            //add the package images
            if($package_list){
                foreach($package_list as $key => &$row)
                {
                    $row->packageImage = $oIssuetrackerModel->getPackageImages($row->package_srl);
                }
            }

            Context::set('package_list', $package_list);
            Context::set('page_navigation', $page_navigation);

            $this->setTemplateFile('download');
        }

        function dispIssuetrackerMessage($msg_code) {
            $msg = Context::getLang($msg_code);
            if(!$msg) $msg = $msg_code;
            Context::set('message', $msg);
            $this->setTemplateFile('message');
        }

        function alertMessage($message) {
            $script =  sprintf('<script type="text/javascript"> xAddEventListener(window,"load", function() { alert("%s"); } );</script>', Context::getLang($message));
            Context::addHtmlHeader( $script );
        }

        function dispIssuetrackerDeleteTrackback() {
            $trackback_srl = Context::get('trackback_srl');

            $oTrackbackModel = &getModel('trackback');
            $output = $oTrackbackModel->getTrackback($trackback_srl);
            $trackback = $output->data;

            if(!$trackback) return $this->dispIssuetrackerMessage('msg_invalid_request');

            Context::set('trackback',$trackback);

            // javascript 필터 추가
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');

            $this->setTemplateFile('delete_trackback');
        }

    }
?>
