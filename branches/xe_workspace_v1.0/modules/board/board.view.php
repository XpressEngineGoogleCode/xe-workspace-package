<?php
    /**
     * @class  boardView
     * @author NHN (developers@xpressengine.com)
     * @brief  board 모듈의 View class
     **/

    class boardView extends board {
		var $listConfig;
		var $columnList;

        /**
         * @brief 초기화
         * board 모듈은 일반 사용과 관리자용으로 나누어진다.\n
         **/
        function init() {
			$oSecurity = new Security();
			$oSecurity->encodeHTML('document_srl', 'comment_srl', 'vid', 'mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type', 'trackback_srl');

            /**
             * 기본 모듈 정보들 설정 (list_count, page_count는 게시판 모듈 전용 정보이고 기본 값에 대한 처리를 함)
             **/
            if($this->module_info->list_count) $this->list_count = $this->module_info->list_count;
            if($this->module_info->search_list_count) $this->search_list_count = $this->module_info->search_list_count;
            if($this->module_info->page_count) $this->page_count = $this->module_info->page_count;
            $this->except_notice = $this->module_info->except_notice == 'N' ? false : true;

            /**
             * 상담 기능 체크. 현재 게시판의 관리자이면 상담기능을 off시킴
             * 현재 사용자가 비로그인 사용자라면 글쓰기/댓글쓰기/목록보기/글보기 권한을 제거함
             **/
            if($this->module_info->consultation == 'Y' && !$this->grant->manager) {
                $this->consultation = true;
                if(!Context::get('is_logged')) $this->grant->list = $this->grant->write_document = $this->grant->write_comment = $this->grant->view = false;
            } else {
                $this->consultation = false;
            }

            /**
             * 스킨 경로를 미리 template_path 라는 변수로 설정함
             * 스킨이 존재하지 않는다면 xe_board로 변경
             **/
            $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            if(!is_dir($template_path)||!$this->module_info->skin) {
                $this->module_info->skin = 'xe_board';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }
            $this->setTemplatePath($template_path);

            /**
             * 확장 변수 사용시 미리 확장변수의 대상 키들을 가져와서 context set
             **/
            $oDocumentModel = &getModel('document');
            $extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
            Context::set('extra_keys', $extra_keys);

			/**
			 * 확장 병수를 통한 소트 기능을 추가하기위해 order_target에 확장변수 키값 추가
			 **/
			if (is_array($extra_keys)){
				foreach($extra_keys as $val){
					$this->order_target[] = $val->eid;
				}
			}
            /**
             * 게시판 전반적으로 사용되는 javascript, JS 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
            Context::addJsFile($this->module_path.'tpl/js/board.js');

			// remove [document_srl]_cpage from get_vars
			$args = Context::getRequestVars();
			foreach($args as $name => $value)
			{
				if(preg_match('/[0-9]+_cpage/', $name))
				{
					Context::set($name, '', TRUE);
					Context::set($name, $value);
				}
			}
        }

        /**
         * @brief 목록 및 선택된 글 출력
         **/
        function dispBoardContent() {
            /**
             * 목록보기 권한 체크 (모든 권한은 ModuleObject에서 xml 정보와 module_info의 grant 값을 비교하여 미리 설정하여 놓음)
             **/
            if((!$this->grant->access || !$this->grant->list)
                    &&($this->module_info->skin != 'xe_board_packageTip'
                        && $this->module_info->skin != 'xe_board_packageLink'
                        && $this->module_info->skin != 'xe_board_File'))
                  return $this->dispBoardMessage('msg_not_permitted');

            /**
             * 카테고리를 사용하는지 확인후 사용시 카테고리 목록을 구해와서 Context에 세팅
             **/
            $this->dispBoardCategoryList();

            /**
             * 목록이 노출될때 같이 나오는 검색 옵션을 정리하여 스킨에서 쓸 수 있도록 context set
             * 확장변수에서 검색 선택된 항목이 있으면 역시 추가
             **/
            // 템플릿에서 사용할 검색옵션 세팅 (검색옵션 key값은 미리 선언되어 있는데 이에 대한 언어별 변경을 함)
            foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
            if($this->module_info->skin == 'xe_board_File')
            {
                $search_option['title'] = 'Folder';
                unset($search_option['content']);
                unset($search_option['comment']);
                unset($search_option['title_content']);
                unset($search_option['tag']);
            }

            $extra_keys = Context::get('extra_keys');
            if($extra_keys) {
                foreach($extra_keys as $key => $val) {
                    if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
                }
            }
            Context::set('search_option', $search_option);

			$oDocumentModel = &getModel('document');
			$statusNameList = $this->_getStatusNameList($oDocumentModel);
			if(count($statusNameList) > 0) Context::set('status_list', $statusNameList);

            // 게시글을 가져옴
            $this->dispBoardContentView();

			// list config, columnList setting
            $oBoardModel = &getModel('board');
			$this->listConfig = $oBoardModel->getListConfig($this->module_info->module_srl);
			$this->_makeListColumnList();

            // 공지사항 목록을 구해서 context set (공지사항을 매페이지 제일 상단에 위치하기 위해서)
            $this->dispBoardNoticeList();

            // 목록
            $this->dispBoardContentList();

            $total_tag_list = $this->_getTags();
            Context::set('total_tag_list',$total_tag_list);

            /**
             * 사용되는 javascript 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');

			$oSecurity = new Security();
			$oSecurity->encodeHTML('search_option.');

            // template_file을 list.html로 지정
            $this->setTemplateFile('list');
        }

        /**
         * @brief 카테고리 항목을 구해와서 스킨에서 사용할 수 있도록 세팅
         **/
        function dispBoardCategoryList(){
            // 카테고리를 사용할때에만 데이터를 추출
            if($this->module_info->use_category=='Y') {
                $oDocumentModel = &getModel('document');
                Context::set('category_list', $oDocumentModel->getCategoryList($this->module_srl));

				$oSecurity = new Security();
				$oSecurity->encodeHTML('category_list.', 'category_list.childs.');
            }
        }

        /**
         * @brief 선택된 게시글이 있을 경우 글을 가져와서 스킨에서 사용하도록 세팅
         **/
        function dispBoardContentView(){
            // 요청된 변수 값들을 정리
            $document_srl = Context::get('document_srl');
            $page = Context::get('page');

            // document model 객체 생성
            $oDocumentModel = &getModel('document');

            /**
             * 요청된 문서 번호가 있다면 문서를 구함
             **/
            if($document_srl) {
                $oDocument = $oDocumentModel->getDocument($document_srl, false, true);

                // 해당 문서가 존재할 경우 필요한 처리를 함
                if($oDocument->isExists()) {

                    // 글과 요청된 모듈이 다르다면 오류 표시
                    if($oDocument->get('module_srl')!=$this->module_info->module_srl ) return $this->stop('msg_invalid_request');

                    // 관리 권한이 있다면 권한을 부여
                    if($this->grant->manager) $oDocument->setGrant();

                    // 상담기능이 사용되고 공지사항이 아니고 사용자의 글도 아니면 무시
                    if($this->consultation && !$oDocument->isNotice()) {
                        $logged_info = Context::get('logged_info');
                        if($oDocument->get('member_srl')!=$logged_info->member_srl) $oDocument = $oDocumentModel->getDocument(0);
                    }

                // 요청된 문서번호의 문서가 없으면 document_srl null 처리 및 경고 메세지 출력
                } else {
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_founded');
                }

            /**
             * 요청된 문서 번호가 아예 없다면 빈 문서 객체 생성
             **/
            } else {
                $oDocument = $oDocumentModel->getDocument(0);
            }

            /**
             * 글 보기 권한을 체크해서 권한이 없으면 오류 메세지 출력하도록 처리
             **/
            if($oDocument->isExists()) {
                if(!$this->grant->view && !$oDocument->isGranted()) {
                    $oDocument = $oDocumentModel->getDocument(0);
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_permitted');
                } else {
                    // 브라우저 타이틀에 글의 제목을 추가
                    Context::addBrowserTitle($oDocument->getTitleText());

                    // 조회수 증가 (비밀글일 경우 권한 체크)
                    if(!$oDocument->isSecret() || $oDocument->isGranted()) $oDocument->updateReadedCount();

                    // 비밀글일때 컨텐츠를 보여주지 말자.
                    if($oDocument->isSecret() && !$oDocument->isGranted()) $oDocument->add('content',Context::getLang('thisissecret'));
                }
            }

            // 스킨에서 사용할 oDocument 변수 세팅
            $oDocument->add('module_srl', $this->module_srl);
            Context::set('oDocument', $oDocument);

            //if the skin is xe_board_file get File List
            if($this->module_info->skin == 'xe_board_File' && $document_srl)
            {
                $fileList = $this->_getFileList();
                Context::set('file_list', $fileList->data);
                Context::set('page_file_navigation', $fileList->page_navigation);
            }

            /**
             * 사용되는 javascript 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

//            return new Object();
        }

        function _getFileList()
        {
            $document_srl = Context::get('document_srl');
            $page = Context::get('page') ? Context::get('page'):1;

            //board model 객체 생성
            $oBoardModel = &getModel('board');
            $obj->page = $page;
            $obj->upload_target_srl = $document_srl;
            $output = $oBoardModel->getFileList($obj);
            return $output;
        }

        /**
         * @brief 선택된 글이 있을 경우 첨부파일에 대한 정보를 API 에서 사용할 수 있도록 세팅
         **/
        function dispBoardContentFileList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            Context::set('file_list',$oDocument->getUploadedFiles());

			$oSecurity = new Security();
			$oSecurity->encodeHTML('file_list..source_filename');
        }

        /**
         * @brief 선택된 글이 있을 경우 그 글의 댓글 목록을 API 에서 사용할 수 있도록 세팅
         **/
        function dispBoardContentCommentList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            $comment_list = $oDocument->getComments();

            // 비밀글일때 컨텐츠를 보여주지 말자.
			if(is_array($comment_list))
			{
				foreach($comment_list as $key => $val){
					if(!$val->isAccessible()){
						$val->add('content',Context::getLang('thisissecret'));
					}
				}
			}
            Context::set('comment_list',$comment_list);

        }

        /**
         * @brief 공지사항이 있을 경우 API에서 사용할 수 있게 하기 위해서 세팅
         **/
        function dispBoardNoticeList(){
            $oDocumentModel = &getModel('document');
            $args->module_srl = $this->module_srl;
            $notice_output = $oDocumentModel->getNoticeList($args, $this->columnList);
            Context::set('notice_list', $notice_output->data);
        }

        /**
         * @brief 게시글 목록
         **/
        function dispBoardContentList(){
            // 만약 목록 보기 권한이 없을 경우 목록을 보여주지 않음
            if(!$this->grant->list) {
                Context::set('document_list', array());
                Context::set('total_count', 0);
                Context::set('total_page', 1);
                Context::set('page', 1);
                Context::set('page_navigation', new PageHandler(0,0,1,10));
                return;
            }

            $oDocumentModel = &getModel('document');

            // 목록을 구하기 위한 대상 모듈/ 페이지 수/ 목록 수/ 페이지 목록 수에 대한 옵션 설정
            $args->module_srl = $this->module_srl;
            $args->page = Context::get('page');
            $args->list_count = $this->list_count;
            $args->page_count = $this->page_count;

            // 검색과 정렬을 위한 변수 설정
            $args->search_target = Context::get('search_target');
            $args->search_keyword = Context::get('search_keyword');

            // 카테고리를 사용한다면 카테고리 값을 받음
            if($this->module_info->use_category=='Y') $args->category_srl = Context::get('category'); ///< 카테고리 사용시 선택된 카테고리

            // 지정된 정렬값이 없다면 스킨에서 설정한 정렬 값을 이용함
            $args->sort_index = Context::get('sort_index');
            $args->order_type = Context::get('order_type');
            if(!in_array($args->sort_index, $this->order_target)) $args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
            if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';

            // 특정 문서의 permalink로 직접 접속할 경우 page값을 직접 구함
            $_get = $_GET;
            if(!$args->page && ($_GET['document_srl'] || $_GET['entry'])) {
                $oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
                if($oDocument->isExists() && !$oDocument->isNotice()) {
                    $page = $oDocumentModel->getDocumentPage($oDocument, $args);
                    Context::set('page', $page);
                    $args->page = $page;
                }
            }

            // 만약 카테고리가 있거나 검색어가 있으면list_count를 search_list_count 로 이용
            if($args->category_srl || $args->search_keyword) $args->list_count = $this->search_list_count;

            // 상담 기능이 on되어 있으면 현재 로그인 사용자의 글만 나타나도록 옵션 변경
            if($this->consultation) {
                $logged_info = Context::get('logged_info');
                $args->member_srl = $logged_info->member_srl;
            }

            if($this->module_info->skin == 'xe_board_File')
            {
                $this->_getFileInfo();
            }

            // 목록 설정값을 세팅
            Context::set('list_config', $this->listConfig);
            // 일반 글을 구해서 context set
            $output = $oDocumentModel->getDocumentList($args, $this->except_notice, true, $this->columnList);
            Context::set('document_list', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);
        }

        function _getFileInfo()
        {
            $oDocumentModel = &getModel('board');
            if(Context::get('document_srl'))
            {
                $target_srl = Context::get('document_srl');
            }
            $logged_info = Context::get('logged_info');
            $member_srl = $logged_info->member_srl ? $logged_info->member_srl:0;
            $output = $oDocumentModel->getToltalFileNum($this->module_srl,$target_srl,$member_srl);
            $filesNum = 0;
            $filesSize = 0;
            if($output->data && $this->module_srl)
            {
                $fileNum = $output->data->count;
                $filesSize = $output->data->file_size;
            }
            Context::set('file_num', $fileNum);
            Context::set('file_size', $filesSize);
        }

		function _makeListColumnList()
		{
			$configColumList = array_keys($this->listConfig);
			$tableColumnList = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'is_notice',
					'title', 'title_bold', 'title_color', 'content', 'readed_count', 'voted_count',
					'blamed_count', 'comment_count', 'trackback_count', 'uploaded_count', 'password', 'user_id',
					'user_name', 'nick_name', 'member_srl', 'email_address', 'homepage', 'tags', 'extra_vars',
					'regdate', 'last_update', 'last_updater', 'ipaddress', 'list_order', 'update_order',
					'allow_trackback', 'notify_message', 'status', 'comment_status');
			$this->columnList = array_intersect($configColumList, $tableColumnList);

			if(in_array('summary', $configColumList)) array_push($this->columnList, 'content');

			// default column list add
			$defaultColumn = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'member_srl', 'last_update', 'comment_count', 'trackback_count', 'uploaded_count', 'status', 'regdate', 'title_bold', 'title_color');

			//TODO 방명록, 블로그 형식을 위한 레거시 지원 코드. 차기 버전에서 다른 방식 고민 필요
			if($this->module_info->skin == 'xe_guestbook' || $this->module_info->default_style == 'blog')
			{
				$defaultColumn = $tableColumnList;
			}

			if (in_array('last_post', $configColumList)){
				array_push($this->columnList, 'last_updater');
			}

			// add is_notice
			if ($this->except_notice) {
				array_push($this->columnList, 'is_notice');
			}
			$this->columnList = array_merge($this->columnList, $defaultColumn);

			// add table name
			foreach($this->columnList as $no => $value)
			{
				$this->columnList[$no] = 'documents.' . $value;
			}
		}

		function _getTags()
		{
		    // 태그 모델 객체에서 태그 목록을 구해옴
            $oTagModel = &getModel('tag');

            $obj->mid = $this->module_info->mid;
            $obj->list_count = 10000;
            $output = $oTagModel->getTagList($obj);

            // 내용을 랜덤으로 정렬
            if(!count($output->data)) {
                return array();
            }
            $numbers = array_keys($output->data);
            shuffle($numbers);

            if(count($output->data)) {
                foreach($numbers as $k => $v) {
                    $tag_list[] = $output->data[$v];
                }
            }
            return $tag_list;
		}

        /**
         * @brief 태그 목록 모두 보기
         **/
        function dispBoardTagList() {
            // 만약 목록 보기 권한조치 없을 경우 태그 목록도 보여주지 않음
            if(!$this->grant->list) return $this->dispBoardMessage('msg_not_permitted');

            $tag_list = $this->_getTags();

            Context::set('tag_list', $tag_list);

			$oSecurity = new Security();
			$oSecurity->encodeHTML('tag_list.');

            $this->setTemplateFile('tag_list');
        }

        /**
         * @brief 글 작성 화면 출력
         **/
        function dispBoardWrite() {
            // 권한 체크
            if(!$this->grant->write_document) return $this->dispBoardMessage('msg_not_permitted');

            $oDocumentModel = &getModel('document');

            /**
             * 카테고리를 사용하는지 확인후 사용시 카테고리 목록을 구해와서 Context에 세팅, 권한도 함께 체크
             **/
            if($this->module_info->use_category=='Y') {
                // 로그인한 사용자의 그룹 정보를 구함
                if(Context::get('is_logged')) {
                    $logged_info = Context::get('logged_info');
                    $group_srls = array_keys($logged_info->group_list);
                } else {
                    $group_srls = array();
                }
                $group_srls_count = count($group_srls);

                // 카테고리 목록을 구하고 권한을 체크
                $normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
                if(count($normal_category_list)) {
                    foreach($normal_category_list as $category_srl => $category) {
                        $is_granted = true;
                        if($category->group_srls) {
                            $category_group_srls = explode(',',$category->group_srls);
                            $is_granted = false;
                            if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = true;

                        }
                        if($is_granted) $category_list[$category_srl] = $category;
                    }
                }
                Context::set('category_list', $category_list);
            }

            // GET parameter에서 document_srl을 가져옴
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
            $oDocument->setDocument($document_srl);
			if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = true;
            $oDocument->add('module_srl', $this->module_srl);

            // 글을 수정하려고 할 경우 권한이 없는 경우 비밀번호 입력화면으로
			$oModuleModel = &getModel('module');
            if($oDocument->isExists()&&!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');
            if(!$oDocument->isExists()) {
                $point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
                $logged_info = Context::get('logged_info');
                $oPointModel = &getModel('point');
                $pointForInsert = $point_config["insert_document"];
                if($pointForInsert < 0) {
                    if( !$logged_info ) return $this->dispBoardMessage('msg_not_permitted');
                    else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 ) return $this->dispBoardMessage('msg_not_enough_point');
                }
            }
			if(!$oDocument->get('status')) $oDocument->add('status', $oDocumentModel->getDefaultStatus());

			$statusList = $this->_getStatusNameList($oDocumentModel);
			if(count($statusList) > 0) Context::set('status_list', $statusList);
			// get Document status config value
            Context::set('document_srl',$document_srl);
            Context::set('oDocument', $oDocument);

            // 확장변수처리를 위해 xml_js_filter를 직접 header에 적용
            $oDocumentController = &getController('document');
            $oDocumentController->addXmlJsFilter($this->module_info->module_srl);

            // 존재하는 글이면 확장변수 값을 context set
            if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());

            /**
             * 사용되는 javascript 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');

			$oSecurity = new Security();
			$oSecurity->encodeHTML('category_list.text', 'category_list.title');
			Context::set('isAddFile', Context::get('isAddFile'));

            $this->setTemplateFile('write_form');
        }

		function _getStatusNameList(&$oDocumentModel)
		{
			$resultList = array();
			if(!empty($this->module_info->use_status))
			{
				$statusNameList = $oDocumentModel->getStatusNameList();
				$statusList = explode('|@|', $this->module_info->use_status);

				if(is_array($statusList))
				{
					foreach($statusList AS $key=>$value)
					{
						$resultList[$value] = $statusNameList[$value];
					}
				}
			}
			return $resultList;
		}

        /**
         * @brief 문서 삭제 화면 출력
         **/
        function dispBoardDelete() {
            // 권한 체크
            if(!$this->grant->write_document) return $this->dispBoardMessage('msg_not_permitted');

            // 삭제할 문서번호를 가져온다
            $document_srl = Context::get('document_srl');

            // 지정된 글이 있는지 확인
            if($document_srl) {
                $oDocumentModel = &getModel('document');
                $oDocument = $oDocumentModel->getDocument($document_srl);
            }

            // 삭제하려는 글이 없으면 에러
            if(!$oDocument->isExists()) return $this->dispBoardContent();

            // 권한이 없는 경우 비밀번호 입력화면으로
            if(!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oDocument',$oDocument);

            /**
             * 필요한 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_document.xml');

            $this->setTemplateFile('delete_form');
        }

        /**
         * @brief 댓글의 답글 화면 출력
         **/
        function dispBoardWriteComment() {
            $document_srl = Context::get('document_srl');

            // 권한 체크
            if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');

            // 원본글을 구함
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            if(!$oDocument->isExists()) return $this->dispBoardMessage('msg_invalid_request');

			// Check allow comment
			if(!$oDocument->allowComment())
			{
				return $this->dispBoardMessage('msg_not_allow_comment');
			}

            // 해당 댓글를 찾아본다 (comment_form을 같이 쓰기 위해서 빈 객체 생성)
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oComment = $oCommentModel->getComment(0);
            $oComment->add('document_srl', $document_srl);
            $oComment->add('module_srl', $this->module_srl);

            // 필요한 정보들 세팅
            Context::set('oDocument',$oDocument);
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);

            /**
             * 필요한 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief 댓글의 답글 화면 출력
         **/
        function dispBoardReplyComment() {
            // 권한 체크
            if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');

            // 목록 구현에 필요한 변수들을 가져온다
            $parent_srl = Context::get('comment_srl');

            // 지정된 원 댓글이 없다면 오류
            if(!$parent_srl) return new Object(-1, 'msg_invalid_request');

            // 해당 댓글를 찾아본다
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);

            // 댓글이 없다면 오류
            if(!$oSourceComment->isExists()) return $this->dispBoardMessage('msg_invalid_request');
            if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl')) return $this->dispBoardMessage('msg_invalid_request');

			// Check allow comment
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($oSourceComment->get('document_srl'));
			if(!$oDocument->allowComment())
			{
				return $this->dispBoardMessage('msg_not_allow_comment');
			}

            // 대상 댓글을 생성
            $oComment = $oCommentModel->getComment();
            $oComment->add('parent_srl', $parent_srl);
            $oComment->add('document_srl', $oSourceComment->get('document_srl'));

            // 필요한 정보들 세팅
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);
            Context::set('module_srl',$this->module_info->module_srl);

            /**
             * 사용되는 javascript 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief 댓글 수정 폼 출력
         **/
        function dispBoardModifyComment() {
            // 권한 체크
            if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');

            // 목록 구현에 필요한 변수들을 가져온다
            $document_srl = Context::get('document_srl');
            $comment_srl = Context::get('comment_srl');

            // 지정된 댓글이 없다면 오류
            if(!$comment_srl) return new Object(-1, 'msg_invalid_request');

            // 해당 댓글를 찾아본다
            $oCommentModel = &getModel('comment');
            $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

            // 댓글이 없다면 오류
            if(!$oComment->isExists()) return $this->dispBoardMessage('msg_invalid_request');

            // 글을 수정하려고 할 경우 권한이 없는 경우 비밀번호 입력화면으로
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

            // 필요한 정보들 세팅
            Context::set('oSourceComment', $oCommentModel->getComment());
            Context::set('oComment', $oComment);

            /**
             * 사용되는 javascript 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief 댓글 삭제 화면 출력
         **/
        function dispBoardDeleteComment() {
            // 권한 체크
            if(!$this->grant->write_comment) return $this->dispBoardMessage('msg_not_permitted');

            // 삭제할 댓글번호를 가져온다
            $comment_srl = Context::get('comment_srl');

            // 삭제하려는 댓글이 있는지 확인
            if($comment_srl) {
                $oCommentModel = &getModel('comment');
                $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
            }

            // 삭제하려는 글이 없으면 에러
            if(!$oComment->isExists() ) return $this->dispBoardContent();

            // 권한이 없는 경우 비밀번호 입력화면으로
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oComment',$oComment);

            /**
             * 필요한 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');

            $this->setTemplateFile('delete_comment_form');
        }

        /**
         * @brief 엮인글 삭제 화면 출력
         **/
        function dispBoardDeleteTrackback() {
            // 삭제할 댓글번호를 가져온다
            $trackback_srl = Context::get('trackback_srl');

            // 삭제하려는 댓글가 있는지 확인
            $oTrackbackModel = &getModel('trackback');
			$columnList = array('trackback_srl');
            $output = $oTrackbackModel->getTrackback($trackback_srl, $columnList);
            $trackback = $output->data;

            // 삭제하려는 글이 없으면 에러
            if(!$trackback) return $this->dispBoardContent();

            //Context::set('trackback',$trackback);	//perhaps trackback variables not use in UI

            /**
             * 필요한 필터 추가
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');

            $this->setTemplateFile('delete_trackback_form');
        }

        /**
         * @brief 메세지 출력
         **/
        function dispBoardMessage($msg_code) {
            $msg = Context::getLang($msg_code);
            if(!$msg) $msg = $msg_code;
            Context::set('message', $msg);
            $this->setTemplateFile('message');
        }

        /**
         * @brief 오류메세지를 system alert로 출력하는 method
         * 특별한 오류를 알려주어야 하는데 별도의 디자인까지는 필요 없을 경우 페이지를 모두 그린후에
         * 오류를 출력하도록 함
         **/
        function alertMessage($message) {
            $script =  sprintf('<script type="text/javascript"> jQuery(function(){ alert("%s"); } );</script>', Context::getLang($message));
            Context::addHtmlFooter( $script );
        }

    }
?>
