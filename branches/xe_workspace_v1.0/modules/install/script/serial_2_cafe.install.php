<?
	// set default layout
	$oModuleController = &getController('module');
	$oModuleModel = &getModel('module');

	// create homepage
	$title = 'Community Sample';
	$domain = 'communitySample';

	$oHomepageAdminController = &getAdminController('homepage');
	$isPackageDefault = true;
	$oHomepageAdminController->insertHomepage($title, $domain, $isPackageDefault);

	// insert Notice Document
	$site_srl = $oHomepageAdminController->get('site_srl');
	$module_info = $oModuleModel->getModuleInfoByMid('notice', $site_srl);

	$oTemplateHandler = &TemplateHandler::getInstance();

	$oDocumentModel = &getModel('document');
	$oDocumentController = &getController('document');

	$obj = NULL;
	$obj->module_srl = $module_info->module_srl;

	global $lang;

	$obj->title = $lang->cafe_welcome_title;
	$lang_code = Context::getLangType();
	$obj->content = $oTemplateHandler->compile('./modules/install/script/cafe_welcome_content', 'welcome_content_'.$lang_code);

	$output = $oDocumentController->insertDocument($obj);
	if(!$output->toBool()) return $output;

	//update notice(annoncement) skin
	$module_info->skin = $forum_skin;
	$output = $oModuleController->updateModule($module_info);
	if(!$output->toBool()) return $output;

	//update freeboard skin
	$module_info = $oModuleModel->getModuleInfoByMid('freeboard', $site_srl);
	$module_info->skin = $forum_skin;
	$output = $oModuleController->updateModule($module_info);
	if(!$output->toBool()) return $output;

	//update the community's layout_srl
	    //get homepage info
	$homepageModel = &getModel('homepage');
	$info = $homepageModel->getHomepageInfo($site_srl);
	if(!$info->first_menu_srl || !$communitySrl) return;

	    //update the community layout's main_menu srl to the new homepage's main_menu
	$menu_srl = $info->first_menu_srl;

    $layoutModel = &getModel('layout');
    $layoutInfo = $layoutModel->getLayout($communitySrl);

    $extra_vars->colorset = $layoutInfo->colorset;
	$extra_vars->main_menu = $menu_srl;
	$extra_vars->bottom_menu = $menu_srl;
	$extra_vars->menu_name_list = array();
	$extra_vars->menu_name_list[$menu_srl] = 'welcome_menu';

    $args = NULL;
    $args->layout_srl = $communitySrl;
    $args->extra_vars = serialize($extra_vars);

    $oLayoutAdminController = &getAdminController('layout');
	$output = $oLayoutAdminController->updateLayout($args);
	if(!$output->toBool()) return $output;

	//update the community's layout to the new community layout
	$args->layout_srl = $communitySrl;
    $args->title = $title;
    $args->site_srl = $site_srl;
    if(!$args->site_srl) return new Object(-1,'msg_invalid_request');

    $output = executeQuery('homepage.updateHomepage', $args);
	if(!$output->toBool()) return $output;

	//register the user
	$oMemberModel = &getModel('member');
	$columnList = array('site_srl', 'group_srl', 'title');
    $default_group = $oMemberModel->getDefaultGroup($site_srl, $columnList);

    $mSrl = $oMemberModel->getLoggedMemberSrl();
    $oMemberController = &getController('member');
    $output = $oMemberController->addMemberToGroup($mSrl, $default_group->group_srl, $site_srl);
    if(!$output->toBool()) return $output;