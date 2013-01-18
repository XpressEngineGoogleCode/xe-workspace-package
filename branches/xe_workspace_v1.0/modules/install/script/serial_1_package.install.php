<?php
    // ko/en/...
	$lang = Context::getLangType();

	// insertMenu
	$menu_args = NULL;
	$menu_args->site_srl = 0;
	$menu_args->title = 'welcome_menu';
	$menu_srl = $menu_args->menu_srl = getNextSequence();
	$menu_args->listorder = $menu_srl * -1;

	$output = executeQuery('menu.insertMenu', $menu_args);
	if(!$output->toBool()) return $output;

	// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->name = 'menu2';
	$parent_srl = $item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

		// create 2depth menuitem
	unset($item_args);
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->url = 'welcome_package_page';
	$item_args->name = 'Our Objective';
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

	// create Layout
		//extra_vars init
	$extra_vars = NULL;
	$extra_vars->colorset = 'default';
	$extra_vars->main_menu = $menu_srl;
	$extra_vars->bottom_menu = $menu_srl;
	$extra_vars->menu_name_list = array();
	$extra_vars->menu_name_list[$menu_srl] = 'welcome_menu';

	$args = NULL;
	$args->site_srl = 0;
	$layout_srl = $args->layout_srl = getNextSequence();
	$args->layout = 'xe_package';
	$args->title = 'welcome_layout';
	$args->layout_type = 'P';

	$oLayoutAdminController = &getAdminController('layout');
	$output = $oLayoutAdminController->insertLayout($args);
	if(!$output->toBool()) return $output;

		// update Layout
	$args->extra_vars = serialize($extra_vars);
	$output = $oLayoutAdminController->updateLayout($args);
	if(!$output->toBool()) return $output;

	// insertPageModule
	$page_args = NULL;
	$page_args->layout_srl = $layout_srl;
	$page_args->browser_title = 'welcome_page';
	$page_args->module = 'page';
	$page_args->mid = 'welcome_package_page';
	$page_args->module_category_srl = 0;
	$page_args->page_caching_interval = 0;
	$page_args->page_type = 'ARTICLE';
	$page_args->skin = 'package';

	$oModuleController = &getController('module');
	$output = $oModuleController->insertModule($page_args);

	if(!$output->toBool()) return $output;

	$module_srl = $output->get('module_srl');

	// insert PageContents - widget
	$oTemplateHandler = &TemplateHandler::getInstance();

	$oDocumentModel = &getModel('document');
	$oDocumentController = &getController('document');

	$obj = NULL;
	$obj->module_srl = $module_srl;
	Context::set('version', __ZBXE_VERSION__);
	$obj->title = 'Our Objective';

	$obj->content = $oTemplateHandler->compile('./modules/install/script/package', 'welcome_content_'.$lang);

	$output = $oDocumentController->insertDocument($obj);
	if(!$output->toBool()) return $output;

	$document_srl = $output->get('document_srl');

	// save PageWidget
	$oModuleModel = &getModel('module');
	$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
	$module_info->document_srl = $document_srl;
	$output = $oModuleController->updateModule($module_info);
	if(!$output->toBool()) return $output;

	// insertFirstModule
	$site_args->site_srl = 0;
	$site_args->index_module_srl = $module_srl;
	$oModuleController->updateSite($site_args);

	//insert Free Talk
	$args = NULL;
	$oAdminModule = &getAdminController('guestbook');
	$packageFreeTalk = $args->guestbook_name = 'packageFreeTalk';
	$args->module_category_srl = 0;
	$args->layout_srl = $layout_srl;
	$args->browser_title = 'Free Talk';
	$args->skin = 'xe_package';
	$args->order_target = 'list_order';
	$args->order_type = 'asc';
	$args->list_count = 20;
	$args->page_count = 10;

	$output = $oAdminModule->procInsertGestBook($args);
	if(!$output->toBool()) return $output;

	//insert GestBook Menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'Free Talk';
	$item_args->url = $packageFreeTalk;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

	//insert board-tip module
	$args = NULL;
	$args->site_srl = 0;
    $args->module_srl = getNextSequence();
    $args->module = 'board';
    $tip = $args->mid = 'board_tips';
    $args->browser_title = 'Tips';
    $args->is_default = 'N';
    $args->layout_srl = $layout_srl;
    $args->skin = 'xe_board_packageTip';

    $oModuleController = &getController('module');
    $output = $oModuleController->insertModule($args);

    $list_arr = array('no','title','nick_name','regdate','read_count','summary','category_srl','tags');
    $oModuleController = &getController('module');
    $oModuleController->insertModulePartConfig('board', $args->module_srl, $list_arr);

    //insert Tips Menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'Tips';
	$item_args->url = $tip;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

	//insert board-link module
	$args = NULL;
	$args->site_srl = 0;
    $args->module_srl = getNextSequence();
    $args->module = 'board';
    $link = $args->mid = 'board_shareLinks';
    $args->browser_title = 'Share Links';
    $args->is_default = 'N';
    $args->layout_srl = $layout_srl;
    $args->skin = 'xe_board_packageLink';

    $oModuleController = &getController('module');
    $output = $oModuleController->insertModule($args);

    $oModuleController = &getController('module');
    $oModuleController->insertModulePartConfig('board', $args->module_srl, $list_arr);

    //insert Tips Menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'Share Links';
	$item_args->url = $link;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

	//insert board-file module
	$args = NULL;
	$args->site_srl = 0;
    $args->module_srl = getNextSequence();
    $args->module = 'board';
    $link = $args->mid = 'board_files';
    $args->browser_title = 'Files';
    $args->is_default = 'N';
    $args->layout_srl = $layout_srl;
    $args->skin = 'xe_board_File';

    $oModuleController = &getController('module');
    $output = $oModuleController->insertModule($args);

    $oModuleController = &getController('module');
    $oModuleController->insertModulePartConfig('board', $args->module_srl, $list_arr);

    //insert skin setting
    $oModuleController = &getController('module');
    $obj = NULL;
    $obj->default_style = 'gallery';
	$output = $oModuleController->insertModuleSkinVars($args->module_srl, $obj);

    //insert Tips Menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'Files';
	$item_args->url = $link;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

    //insert Project_hub Item
    $proAdminObj = &getAdminController('project');

    $args = NULL;
    $pUrl = $args->project_main_mid = 'project';
    $args->layout_srl = $layout_srl;
    $args->browser_title = 'Projects';
    $args->skin = 'xe_pacakgePro';
    $args->access_type = 'vid';
    $args->menu_srl = $menu_srl;

    $output = $proAdminObj->procProjectAdminInsertConfig($args);

    //insert project
    $projSampleId = 'projectId';
    $output = $proAdminObj->procProjectAdminInsertProject('Project Sample', $projSampleId, 'xe_pacakgePro');

    //insert Projects Menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'My Projects';
	$item_args->url = $pUrl;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

    //update the module skin information-issuetracker
    $oModuleModel = &getModel('module');
    $siteInfo = $oModuleModel->getSiteInfoByDomain($projSampleId);
    $projetSiteSrl = $siteInfo->site_srl;
	$proSampleIssueInfo = $oModuleModel->getModuleInfoByMid('issuetracker', $projetSiteSrl);
	$args = $proSampleIssueInfo;
	$args->skin = 'xe_package';
	$args->project_name = 'issuetracker';
	$output = $oModuleController->updateModule($args);

	//insert default priority
	$oIssueAdminContro = &getAdminController('issuetracker');
	$args = NULL;
	$args->mid = 'issuetracker';
	$args->vid = $projSampleId;
	$args->module_srl = $proSampleIssueInfo->module_srl;
    $args->module = 'issuetracker';

	$args->priority_srl = NULL;
	$args->is_default = NULL;
	$args->title = 'Low';
	$output = $oIssueAdminContro->procIssuetrackerAdminInsertPriority($args);

	$args->priority_srl = NULL;
	$args->title = 'Medium';
	$args->is_default = 'Y';
	$output = $oIssueAdminContro->procIssuetrackerAdminInsertPriority($args);

	$args->priority_srl = NULL;
	$args->is_default = NULL;
	$args->title = 'High';
	$output = $oIssueAdminContro->procIssuetrackerAdminInsertPriority($args);

	//update the module skin information-forum
	$proForumInfo = $oModuleModel->getModuleInfoByMid('forum', $projetSiteSrl);
	$args = $proForumInfo;
	$forum_skin = $args->skin = 'xe_package_forum';
	$output = $oModuleController->updateModule($args);
	if(!$output->toBool()) return $output;

	//update forum skin info ,remove skin comment information
    $forumSrl = $proForumInfo->module_srl;
    $forumInfo =  $oModuleModel->getModuleSkinVars($forumSrl);
    $moduleController = &getController('module');
    $obj = $forumInfo;
    $obj['comment'] = NULL;
    $moduleController->insertModuleSkinVars($forumSrl , $obj);

	//update default display order
	$oModuleController = &getController('module');
	$module_config = NULL;
    $module_config->display_option = array('issue_id','status','milestone','priority','writer');
    $oModuleController->insertModulePartConfig('issuetracker',$proSampleIssueInfo->module_srl,$module_config);

    //update the module skin information-wiki
    $proWikiInfo = $oModuleModel->getModuleInfoByMid('wiki', $projetSiteSrl);
	$args = $proWikiInfo;
	$args->skin = 'xe_package';
	$output = $oModuleController->updateModule($args);
	if(!$output->toBool()) return $output;

	//insert cafe_hub(communities)
	// create community Layout
		//extra_vars init
	$extra_vars = NULL;
	$extra_vars->colorset = 'default';
	$extra_vars->main_menu = $menu_srl;
	$extra_vars->bottom_menu = $menu_srl;
	$extra_vars->menu_name_list = array();
	$extra_vars->menu_name_list[$menu_srl] = 'welcome_menu';

	$args = NULL;
	$args->site_srl = 0;
	$communitySrl = $args->layout_srl = getNextSequence();
	$layoutUrl = $args->layout = 'xe_package_community';
	$args->title = 'community_layout';
	$args->layout_type = 'P';

	$oLayoutAdminController = &getAdminController('layout');
	$output = $oLayoutAdminController->insertLayout($args);
	if(!$output->toBool()) return $output;

		// update Layout
	$args->extra_vars = serialize($extra_vars);
	$output = $oLayoutAdminController->updateLayout($args);
	if(!$output->toBool()) return $output;

	$args = NULL;
	$cafeHub = $args->cafe_main_mid = 'cafeHub';
	$args->browser_title = 'My Communities';
	$args->layout_srl = $layout_srl;
	$args->access_type = 'vid';
	$args->enable_change_layout = 'Y';
	$args->allow_service_board = 10;
	$args->allow_service_guestbook = 2;
	$args->allow_service_contact = 2;
	$args->allow_service_faq = 2;
	$args->allow_service_wiki = 2;
	$args->allow_service_page = 2;
	$args->allow_service_issuetracker = 2;
	$args->allow_service_wiki = 2;
	$args->default_layout = $layoutUrl;
	$args->skin = 'xe_package_cafe';

	$cafeAdminController = &getAdminController('homepage');
	$output = $cafeAdminController->procHomepageAdminInsertConfig($args);

	//insert the cafe hub information
	    //get cafehub module_srl
	$oModuleModle = &getModel('module');
	$output = $oModuleModle->getModuleSrlByMid($cafeHub);
    $cafeHubSrl = $output[0];

	    //copy the community hub logo image
	$communityImage = 'communityLogoImage.jpg';
	$source = dirname(__FILE__).'/'.$communityImage;
	$dest = sprintf("./files/attach/images/%s/%s", $cafeHubSrl, $communityImage);
	mkdir(dirname($dest));
	$stat = copy($source, _XE_PATH_.$dest);

	    //insert module skin var
	$obj = NULL;
	$obj->title = 'Community Center';
	$obj->sub_title = '- Welcome to communities center!';
	$obj->comment = 'The cafe site contains a number of web cafes, each of which refers to a community';
	$obj->intro_title = '- Here you will find information on all the items inside communities';
	$obj->intro_description = 'View the basic information about communities information<br/>Find the detailed information (description, links, new etc.) for the communities.<br/>How to get used to work in the community.';
	$obj->intro_image = $dest;
	$oModuleController = &getController('module');
	$output = $oModuleController->insertModuleSkinVars($cafeHubSrl, $obj);
	if(!$output->toBool()) return $output;

	//insert Communities cafe's menu
		// insertMenuItem
		// create 1depth menuitem
	$item_args = NULL;
	$item_args->menu_srl = $menu_srl;
	$item_args->parent_srl = $parent_srl;
	$item_args->name = 'My Communities';
	$item_args->url = $cafeHub;
	$item_args->menu_item_srl = getNextSequence();
	$item_args->listorder = -1*$item_args->menu_item_srl;

	$output = executeQuery('menu.insertMenuItem', $item_args);
	if(!$output->toBool()) return $output;

	//update menu XML cache file
	$oMenuAdminController = &getAdminController('menu');
	$oMenuAdminController->makeXmlFile($menu_srl);

	//add html5 option
	$db_info = Context::getDBInfo();
	$db_info->use_html5 = 'Y';
	Context::setDBInfo($db_info);
	$oInstallController = &getController('install');
    $oInstallController->makeConfigFile();

    //set the member skin
    $oModuleController = &getController('module');
    $args = NULL;
    $args->skin = 'xe_workspace';
    $output = $oModuleController->updateModuleConfig('member', $args);
    if(!$output->toBool()) return $output;

    //set communication skin
    // create the module module Controller object
    $oModuleController = &getController('module');
    $output = $oModuleController->updateModuleConfig('communication',$args);

    //set message module
    $oModuleController = &getController('module');
    $output = $oModuleController->insertModuleConfig('message',$args);