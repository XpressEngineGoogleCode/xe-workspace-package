function completeInsertProject(ret_obj) {
    var site_srl = ret_obj['site_srl'];
    location.href = current_url.setQuery('site_srl',site_srl).setQuery('act','dispProjectAdminSetup');
}

function doProjectInsertAdmin() {
    var fo_obj = xGetElementById("projectFo");
    var sel_obj = fo_obj.admin_list;
    var admin_id = fo_obj.admin_id.value;
    if(!admin_id) return;

    var opt = new Option(admin_id,admin_id,true,true);
    sel_obj.options[sel_obj.options.length] = opt;

    fo_obj.admin_id.value = '';
    sel_obj.size = sel_obj.options.length;
    sel_obj.selectedIndex = -1;
}

function doProjectDeleteAdmin() {
    var fo_obj = xGetElementById("projectFo");
    var sel_obj = fo_obj.admin_list;
    sel_obj.remove(sel_obj.selectedIndex);

    sel_obj.size = sel_obj.options.length;
    sel_obj.selectedIndex = -1;
}

function doUpdateProject(fo_obj, func) {
    var sel_obj = fo_obj.admin_list;
    var arr = new Array();
    for(var i=0;i<sel_obj.options.length;i++) {
        arr[arr.length] = sel_obj.options[i].value;
    }
    fo_obj.project_admin.value = arr.join(',');
    procFilter(fo_obj, func);
    return false;

}

function completeUpdateProject(ret_obj) {
    alert(ret_obj['message']);
    location.reload();
}

function completeDeleteProject(ret_obj) {
    alert(ret_obj['message']);
    location.href = current_url.setQuery('act','dispProjectAdminContent').setQuery('site_srl','');
}

function nodeToggleAll(){
    jQuery("[class*=close]", simpleTreeCollection[0]).each(function(){
        simpleTreeCollection[0].nodeToggle(this);
    });
}


function completeInsertGroup(ret_obj) {
    location.href = current_url.setQuery('group_srl','');
}

function completeDeleteGroup(ret_obj) {
    location.href = current_url.setQuery('group_srl','');

}

function completeInsertGrant(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var page = ret_obj['page'];
    var module_srl = ret_obj['module_srl'];

    alert(message);
}

function doDeleteGroup(group_srl) {
    var fo_obj = xGetElementById('fo_group');
    fo_obj.group_srl.value = group_srl;
    procFilter(fo_obj, delete_group);
}

function doRemoveMember(confirm_msg) {
    var fo_obj = xGetElementById('siteMembers');
    var chk_obj = fo_obj.cart;
    if(!chk_obj) return;


    var values = new Array();
    if(typeof(chk_obj.length)=='undefined') {
        if(chk_obj.checked) values[values.length]=chk_obj.value;
    } else {
        for(var i=0;i<chk_obj.length;i++) {
            if(chk_obj[i].checked) values[values.length]=chk_obj[i].value;
        }
    }
    if(values.length<1) return;

    if(!confirm(confirm_msg)) return;

    params = new Array();
    params['member_srl'] = values.join(',');

    exec_xml('project','procProjectDeleteMember', params, doCompleteRemoveMember);
}

function doCompleteRemoveMember(ret_obj) { 
    alert(ret_obj['message']); 
    location.reload(); 
}

function toggleAccessType(target) {
    switch(target) {
        case 'domain' :
                xGetElementById('projectFo').domain.value = '';
                xGetElementById('accessDomain').style.display = 'block';
                xGetElementById('accessVid').style.display = 'none';
            break;
        case 'vid' :
                xGetElementById('projectFo').vid.value = '';
                xGetElementById('accessDomain').style.display = 'none';
                xGetElementById('accessVid').style.display = 'block';
            break;
    }
}

function toggleProjectAccessType(target) {
    switch(target) {
        case 'domain' :
                xGetElementById('accessProjectDomain').style.display = 'block';
            break;
        case 'vid' :
                xGetElementById('accessProjectDomain').style.display = 'none';
            break;
    }
}

function importModule(id) {
    popopen( request_uri.setQuery('module','module').setQuery('act','dispModuleSelectList').setQuery('id',id).setQuery('type','single'), 'ModuleSelect');
}

function insertSelectedModule(id, module_srl, mid, browser_title) {
    params = new Array();
    params['import_module_srl'] = module_srl;
    params['site_srl'] = xGetElementById('foImport').site_srl.value;
    exec_xml('project','procProjectAdminImportModule', params, doComplenteInsertSelectedModule);
}

function doComplenteInsertSelectedModule(ret_obj) {
    location.reload();
}

function doDeleteDirectory(project_dir_srl) {
    params = new Array();
    params['project_dir_srl'] = project_dir_srl;
    exec_xml('project','procProjectAdminDeleteDirectory', params, function() { location.href = current_url.setQuery('project_dir_srl','') });
}

function completeInsertDirectory(ret_obj) {
    location.href = current_url.setQuery('act','dispProjectAdminDirectorySetup');
}

function completeInsertRepos(ret_obj) {
    alert(ret_obj['message']);
    location.reload();
}

function doDeleteReserve(reserve_srl) {
    params = new Array();
    params['reserve_srl'] = reserve_srl;
    exec_xml('project','procProjectAdminDeleteReserve', params, function() { location.reload(); });
}
