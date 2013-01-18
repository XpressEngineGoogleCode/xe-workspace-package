<?php
    /**
     * @class  homepageModel
     * @author NHN (developers@xpressengine.com)
     * @brief  homepage 모듈의  model class
     **/
    require_once(_XE_PATH_.'modules/document/document.item.php');

    class homepageModel extends homepage {

        var $site_module_info = null;
        var $site_srl = 0;

        function init() {
            // site_module_info값으로 홈페이지의 정보를 구함
            $this->site_module_info = Context::get('site_module_info');
            $this->site_srl = $this->site_module_info->site_srl;
        }

        function getConfig($site_srl = 0) {
            static $configs = array();

            $oModuleModel = &getModel('module');

            if(!isset($configs[$site_srl])) {
                $config = $oModuleModel->getModuleConfig('homepage');
                if(!$config) {
                    $config->default_layout = 'xe_cafe_site';
                    $config->enable_change_layout = 'N';
                    $config->allow_service = array('board'=>10,'page'=>2);
                    $config->creation_group = array();
                    $config->cafe_main_mid = 'cafe';
                    $config->skin = 'xe_cafe_v2';
                    $config->access_type = 'vid';
                    $config->default_domain = '';
                } else {
                    $config->creation_group = explode(',',$config->creation_group);
                    if(!isset($config->cafe_main_mid)) $config->cafe_main_mid = 'cafe';
                    if(!isset($config->skin)) $config->skin = 'xe_cafe_v2';
                    if(!isset($config->access_type)) $config->access_type = 'vid';
                    if($config->default_domain) {
                        if(strpos($config->default_domain,':')===false) $config->default_domain = 'http://'.$config->default_domain;
                        if(substr($config->default_domain,-1)!='/') $config->default_domain .= '/';
                    }
                }
                if($site_srl) {
                    $part_config = $oModuleModel->getModulePartConfig('homepage', $site_srl);
                    if(!$part_config) $part_config = $config;
                    else $config = $part_config;
                }
                $configs[$site_srl] = $config;
            }

            return $configs[$site_srl];
        }

        function isCreationGranted($member_info = null) {
            if(!$member_info) $member_info = Context::get('logged_info');
            if(!$member_info->member_srl) return false;
            if($member_info->is_admin == 'Y') return true;

            $config = $this->getConfig(0);

            if(!is_array($member_info->group_list) || !count($member_info->group_list) || !count($config->creation_group)) return;

            $keys = array_keys($member_info->group_list);
            for($i=0,$c=count($keys);$i<$c;$i++) {
                if(in_array($keys[$i],$config->creation_group)) return true;
            }
            return false;
        }

        function getHomepageInfo($site_srl) {
            static $infos = array();
            if(!isset($infos[$site_srl])) {
                $args->site_srl = $site_srl;
                $output = executeQuery('homepage.getHomepageInfo', $args);
                if(!$output->toBool() || !$output->data) $infos[$site_srl] = null;
                else {
                    $banner_src = 'files/attach/cafe_banner/'.$site_srl.'.jpg';
                    if(file_exists(_XE_PATH_.$banner_src)) $output->data->cafe_banner = $banner_src.'?rnd='.filemtime(_XE_PATH_.$banner_src);
					$output->data->layout_srl = $output->data->cafe_layout_srl;
					$output->data->mlayout_srl = $output->data->cafe_mlayout_srl;
                    $infos[$site_srl] = $output->data;
                }
            }
            return $infos[$site_srl];
        }

        function getHomepageMenuItem() {
            $node_srl = Context::get('node_srl');
            if(!$node_srl) return new Object(-1,'msg_invalid_request');

            $oMenuAdminModel = &getAdminModel('menu');
            $menu_info = $oMenuAdminModel->getMenuItemInfo($node_srl);

            if(!preg_match('/^http/i',$menu_info->url)) {
                $oModuleModel = &getModel('module');
                $module_info = $oModuleModel->getModuleInfoByMid($menu_info->url, $this->site_srl);
                if($module_info->mid == $menu_info->url) {
                    $menu_info->module_type = $module_info->module;
                    $menu_info->module_id = $module_info->mid;
                    $menu_info->browser_title = $module_info->browser_title;
                    unset($menu_info->url);
                }
            } else {
                $menu_info->module_type = 'url';
                $menu_info->url = preg_replace('/^(http|https):\/\//i','',$menu_info->url);
            }
            $this->add('menu_info', $menu_info);
        }

        function getMyHomepage()
        {
            $logged_info = Context::get('logged_info');
            if($logged_info->member_srl) {
                $myargs->member_srl = $logged_info->member_srl;
                $output = executeQueryArray('homepage.getMyCafes', $myargs);
            }
            if(!$output->data) $output->data = array();
            Context::set('my_cafes', $output->data);

            $module_info = $this->module_info;
            $module_path = './modules/'.$module_info->module.'/';
			$skin_path = $module_path.'skins/'.$module_info->skin.'/';
			if(!$module_info->skin || !is_dir($skin_path)) {
				$skin_path = $module_path.'skins/xe_package_cafe/';
			}

            $oTemplateHandler = &TemplateHandler::getInstance();
            $result = new Object();
            $result->add('html', $oTemplateHandler->compile($skin_path, 'myhomepage.html'));
            $this->add('html', $result->get('html'));
        }

		function getHomepageMenuTplInfo(){
            // 해당 메뉴의 정보를 가져오기 위한 변수 설정
            $menu_item_srl = Context::get('menu_item_srl');
            $parent_srl = Context::get('parent_srl');
			$mode = Context::get('mode');

            // 홈페이지 정보
            $oModuleModel = &getModel('module');
            // 회원 그룹의 목록을 가져옴
            $oMemberModel = &getModel('member');
            $group_list = $oMemberModel->getGroups($this->site_srl);
            Context::set('group_list', $group_list);

            // parent_srl이 있고 menu_item_srl이 없으면 하부 메뉴 추가임
			$oMenuAdminModel =  &getAdminModel('menu');
            if($mode == 'insert') {
                // 상위 메뉴의 정보를 가져옴
                $parent_info = $oMenuAdminModel->getMenuItemInfo($parent_srl);
                $item_info->parent_srl = $parent_srl;

            // root에 메뉴 추가하거나 기존 메뉴의 수정일 경우
            } else if ($mode == 'update'){
                // menu_item_srl 이 있으면 해당 메뉴의 정보를 가져온다
                if($menu_item_srl) $item_info = $oMenuAdminModel->getMenuItemInfo($menu_item_srl);
            }

            if(!preg_match('/^http/i',$item_info->url)) {
                $oModuleModel = &getModel('module');
                $module_info = $oModuleModel->getModuleInfoByMid($item_info->url, $this->site_srl);
                if($module_info->mid == $item_info->url) {
                    $item_info->module_type = $module_info->module;
                    $item_info->module_id = $module_info->mid;
                    $item_info->browser_title = $module_info->browser_title;
                    unset($item_info->url);
                }
            } else {
                $item_info->module_type = 'url';
                $item_info->url = preg_replace('/^(http|https):\/\//i','',$item_info->url);
            }
            Context::set('item_info', $item_info);

            $homepage_config = $this->getConfig($this->site_srl);
            if(count($homepage_config->allow_service)) {
                foreach($homepage_config->allow_service as $k => $v) {
                    if($v<1) continue;
                    $c = $oModuleModel->getModuleCount($this->site_srl, $k);
                    $homepage_config->allow_service[$k] -= $c;
                }
            }
            Context::set('homepage_config', $homepage_config);

            // template 파일을 직접 컴파일한후 tpl변수에 담아서 return한다.
            $oTemplate = &TemplateHandler::getInstance();
            $tpl = $oTemplate->compile($this->module_path.'tpl', 'homepage_menu_item_info');

			$oModuleController = &getController('module');
			$oModuleController->replaceDefinedLangCode(&$tpl);

            $this->add('tpl', str_replace("\n"," ",$tpl));

		}

		function getHomepageList($args) {

            if(!$args->page) $args->page = 1;
			$args->s_keyword = Context::get('s_keyword');
            $output = executeQueryArray('homepage.getHomepageList', $args);
            return $output;
        }

        function getMyHomePageList($args)
        {
            if(!$args->page) $args->page = 1;
			$args->s_keyword = Context::get('s_keyword');
            $output = executeQueryArray('homepage.getMyCafeList', $args);
            return $output;
        }

        function getHomepageNews()
        {
            $args = NULL;
            $isAll = Context::get('isAll');
            if(!$isAll) $isAll = Context::get('isall');
            if($isAll != 'Y')
            {
                $logged_info = Context::get('logged_info');
                if(!$logged_info)
                {
                    $args->member_srl = 0;
                }
                else
                {
                    $args->member_srl = $logged_info->member_srl;
                }
                $funcName = 'getMyHomePageList';
            }
            $page = Context::get('page');
            $args->page = $page ? $page:1;

            $output = executeQueryArray('homepage.getNewestDocuments',$args);

            if($output->data) {
                foreach($output->data as $key => $attribute) {
                    $document_srl = $attribute->document_srl;
                    if(!$GLOBALS['XE_DOCUMENT_LIST'][$document_srl]) {
                        unset($oDocument);
                        $oDocument = new documentItem();
                        $oDocument->setAttribute($attribute, false);
                        $GLOBALS['XE_DOCUMENT_LIST'][$document_srl] = $oDocument;
                    }
                    $output->data[$key] = $GLOBALS['XE_DOCUMENT_LIST'][$document_srl];
                }
            }
            Context::set('newest_documents', $output->data);

            $module_info = $this->module_info;
            $module_path = './modules/'.$module_info->module.'/';
			$skin_path = $module_path.'skins/'.$module_info->skin.'/';
			if(!$module_info->skin || !is_dir($skin_path)) {
				$skin_path = $module_path.'skins/xe_package_cafe/';
			}

            $oTemplateHandler = &TemplateHandler::getInstance();
            $result = new Object();
            $result->add('html', $oTemplateHandler->compile($skin_path, 'news.html'));
            $this->add('html', $result->get('html'));
        }

        function getHomepageBanner($siteSrl)
        {
            $banner_src = 'files/attach/cafe_banner/'.$siteSrl.'.jpg';
            if(file_exists(_XE_PATH_.$banner_src)) $cafe_banner = $banner_src.'?rnd='.filemtime(_XE_PATH_.$banner_src);
            else $cafe_banner = '';
            return $cafe_banner;
        }
    }

?>
