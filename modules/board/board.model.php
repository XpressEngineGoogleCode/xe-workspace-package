<?php
    /**
     * @class  boardModel
     * @author NHN (developers@xpressengine.com)
     * @brief  board 모듈의 Model class
     **/

    class boardModel extends module {
        /**
         * @brief 초기화
         **/
        function init() {
        }

        function getToltalFileNum($module_srl,$target_srl = NULL,$member_srl = 0)
        {
            $args->module_srl = $module_srl;
            $args->member_srl = $member_srl;
            if(!empty($target_srl)) $args->upload_target_srl = $target_srl;
            $output = executeQuery('board.getFilesInfo', $args);
            return $output;
        }

        /**
         * @brief 목록 설정 값을 가져옴
         **/
        function getListConfig($module_srl) {
            $oModuleModel = &getModel('module');
            $oDocumentModel = &getModel('document');

            // 저장된 목록 설정값을 구하고 없으면 기본 값으로 설정
            $list_config = $oModuleModel->getModulePartConfig('board', $module_srl);
            if(!$list_config || !count($list_config)) $list_config = array( 'no', 'title', 'nick_name','regdate','readed_count');

            // 사용자 선언 확장변수 구해와서 배열 변환후 return
            $inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

            foreach($list_config as $key) {
                if(preg_match('/^([0-9]+)$/',$key)) $output['extra_vars'.$key] = $inserted_extra_vars[$key];
                else $output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
            }
            return $output;
        }

        function getFileList($obj, $columnList = array()) {
			$this->_makeSearchParam($obj, $args);

            // Set valid/invalid state
            if($obj->isvalid == 'Y') $args->isvalid = 'Y';
            elseif($obj->isvalid == 'N') $args->isvalid = 'N';
            // Set multimedia/common file
            if($obj->direct_download == 'Y') $args->direct_download = 'Y';
            elseif($obj->direct_download == 'N') $args->direct_download= 'N';
            // Set variables
            $args->sort_index = $obj->sort_index;
            $args->page = $obj->page?$obj->page:1;
            $args->list_count = $obj->list_count?$obj->list_count:20;
            $args->page_count = $obj->page_count?$obj->page_count:10;
            $args->s_module_srl = $obj->module_srl;
            $args->exclude_module_srl = $obj->exclude_module_srl;
            $args->upload_target_srl = $obj->upload_target_srl;
            // Execute the file.getFileList query
            $output = executeQuery('board.getFileList', $args, $columnList);
            // Return if no result or an error occurs
            if(!$output->toBool()||!count($output->data)) return $output;

            $oFileModel = &getModel('file');

            foreach($output->data as $key => $file) {
				if($_SESSION['file_management'][$file->file_srl]) $file->isCarted = true;
				else $file->isCarted = false;

                $file->download_url = $oFileModel->getDownloadUrl($file->file_srl, $file->sid);
                $output->data[$key] = $file;
            }

            return $output;
        }

        function _makeSearchParam(&$obj, &$args)
		{
            // Search options
            $search_target = $obj->search_target?$obj->search_target:trim(Context::get('search_target'));
            $search_keyword = $obj->search_keyword?$obj->search_keyword:trim(Context::get('search_keyword'));

            if($search_target && $search_keyword) {
                switch($search_target) {
                    case 'filename' :
                            if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
                            $args->s_filename = $search_keyword;
                        break;
                    case 'filesize_more' :
                            $args->s_filesize_more = (int)$search_keyword;
                        break;
                    case 'filesize_mega_more' :
                            $args->s_filesize_more = (int)$search_keyword * 1024 * 1024;
                        break;
					case 'filesize_less' :
                            $args->s_filesize_less = (int)$search_keyword;
                        break;
					case 'filesize_mega_less' :
                            $args->s_filesize_less = (int)$search_keyword * 1024 * 1024;
                        break;
                    case 'download_count' :
                            $args->s_download_count = (int)$search_keyword;
                        break;
                    case 'regdate' :
                            $args->s_regdate = $search_keyword;
                        break;
                    case 'ipaddress' :
                            $args->s_ipaddress = $search_keyword;
                        break;
                    case 'user_id' :
                            $args->s_user_id = $search_keyword;
                        break;
                    case 'user_name' :
                            $args->s_user_name = $search_keyword;
                        break;
                    case 'nick_name' :
                            $args->s_nick_name = $search_keyword;
                        break;
                    case 'isvalid' :
                            $args->isvalid = $search_keyword;
                        break;
                }
            }
		}

        /**
         * @brief 기본 목록 설정값을 return
         **/
        function getDefaultListConfig($module_srl) {
            // 가상번호, 제목, 등록일, 수정일, 닉네임, 아이디, 이름, 조회수, 추천수 추가
            $virtual_vars = array( 'no', 'title', 'regdate', 'last_update', 'last_post', 'nick_name',
					'user_id', 'user_name', 'readed_count', 'voted_count', 'blamed_count', 'thumbnail', 'summary');
            foreach($virtual_vars as $key) {
                $extra_vars[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
            }

            // 사용자 선언 확장변수 정리
            $oDocumentModel = &getModel('document');
            $inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

            if(count($inserted_extra_vars)) foreach($inserted_extra_vars as $obj) $extra_vars['extra_vars'.$obj->idx] = $obj;

            return $extra_vars;

        }

        /**
         * @brief return module name in sitemap
         **/
		function triggerModuleListInSitemap(&$obj)
		{
			array_push($obj, 'board');
		}
    }
?>
