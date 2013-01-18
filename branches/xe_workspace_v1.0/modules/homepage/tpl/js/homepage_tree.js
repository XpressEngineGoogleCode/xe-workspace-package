function homepageLoadMenuInfo(url){
    // clear tree;
    jQuery('#menu > ul > li > ul').remove();
    if(jQuery("ul.simpleTree > li > a").size() ==0)jQuery('<a href="#" class="add"><img src="./common/js/plugins/ui.tree/images/iconAdd.gif" /></a>').bind("click",function(e){homepageAddMenu(0,e);}).appendTo("ul.simpleTree > li");

    //ajax get data and transeform ul il
    jQuery.get(url,function(data){
        jQuery(data).find("node").each(function(i){
            var text = jQuery(this).attr("text");
            var node_srl = jQuery(this).attr("node_srl");
            var parent_srl = jQuery(this).attr("parent_srl");
            var url = jQuery(this).attr("url");

            // node
            var node = jQuery('<li id="tree_'+node_srl+'"><span>'+text+'</span></li>');

            // button
            jQuery('<a href="#" class="add"><img src="./common/js/plugins/ui.tree/images/iconAdd.gif" /></a>').bind("click",function(e){
                jQuery("#tree_"+node_srl+" > span").click();
                homepageAddMenu(node_srl,e);
                return false;
            }).appendTo(node);

            jQuery('<a href="#" class="modify"><img src="./common/js/plugins/ui.tree/images/iconModify.gif" /></a>').bind("click",function(e){
				jQuery("#tree_"+node_srl+" > span").click();
                homepageModifyNode(node_srl,e);
                return false;

            }).appendTo(node);

            jQuery('<a href="#" class="delete"><img src="./common/js/plugins/ui.tree/images/iconDel.gif" /></a>').bind("click",function(e){
                homepageDeleteMenu(node_srl);
                return false;
            }).appendTo(node);

            // insert parent child
            if(parent_srl>0){
                if(jQuery('#tree_'+parent_srl+'>ul').length==0) jQuery('#tree_'+parent_srl).append(jQuery('<ul>'));
                jQuery('#tree_'+parent_srl+'> ul').append(node);
            }else{
                if(jQuery('#menu ul.simpleTree > li > ul').length==0) jQuery("<ul>").appendTo('#menu ul.simpleTree > li');
                jQuery('#menu ul.simpleTree > li > ul').append(node);
            }

        });

        //button show hide
        jQuery("#menu li").each(function(){
            if(jQuery(this).parents('ul').size() > max_menu_depth) jQuery("a.add",this).hide();
            if(jQuery(">ul",this).size()>0) jQuery(">a.delete",this).hide();
        });


        // draw tree
        simpleTreeCollection = jQuery('.simpleTree').simpleTree({
            autoclose: false,
            afterClick:function(node){
                //alert("text-"+jQuery('span:first',node).text());
            },
            afterDblClick:function(node){
                //alert("text-"+jQuery('span:first',node).text());
            },
            afterMove:function(destination, source, pos){
                jQuery('#menuItem').css("display",'none');
                if(destination.size() == 0){
                    homepageLoadMenuInfo(xml_url);
                    return;
                }
                var menu_srl = jQuery("#fo_menu input[name=menu_srl]").val();
                var parent_srl = destination.attr('id').replace(/.*_/g,'');
                var target_srl = source.attr('id').replace(/.*_/g,'');
                var brothers = jQuery('#'+destination.attr('id')+' > ul > li:not([class^=line])').length;
                var mode = brothers >1 ? 'move':'insert';
                var source_srl = pos == 0 ? 0: source.prevAll("li:not(.line)").get(0).id.replace(/.*_/g,'');

                jQuery.exec_json("homepage.procHomepageMenuItemMove",{ "menu_srl":menu_srl,"parent_srl":parent_srl,"target_srl":target_srl,"source_srl":source_srl,"mode":mode},
                function(data){
                    if(data.error>0){
                        homepageLoadMenuInfo(xml_url);
                    }
                });
            },

            // i want you !! made by sol
            beforeMovedToLine : function(destination, source, pos){
                return (jQuery(destination).parents('ul').size() + jQuery('ul',source).size() <= max_menu_depth);
            },

            // i want you !! made by sol
            beforeMovedToFolder : function(destination, source, pos){
                return (jQuery(destination).parents('ul').size() + jQuery('ul',source).size() <= max_menu_depth-1);
            },
            afterAjax:function()
            {
                //alert('Loaded');
            },
            animate:true
            ,docToFolderConvert:true
        });

        // open all node
        nodeToggleAll();
    },"xml");
}
function doReloadTreeMenu(){
    var menu_srl = jQuery("#fo_menu input[name=menu_srl]").val();

    jQuery.exec_json("menu.procMenuAdminMakeXmlFile",{ "menu_srl":menu_srl},
            function(data){
                 homepageLoadMenuInfo(xml_url);
            }
    );
    jQuery('#menuItem').css("display",'none');
    menuFormReset();
}

function closeTreeMenuInfo(){
    jQuery('#menu_zone_info').css("display",'none');
}

function completeInsertMenuItem(data) {
    var xml_file = data['xml_file'];
    if(!xml_file) return;
    homepageLoadMenuInfo(xml_url);
    jQuery('#menu_zone_info').css("display",'none');
    menuFormReset();
}


function homepageAddMenu(node_srl,e) {
    jQuery('#menu_zone_info').html('');
    jQuery("#tree_"+node_srl+" > span").click();

    var params ={
            "menu_item_srl":0
            ,"parent_srl":node_srl
			,"mode":"insert"
            };

    jQuery.exec_json('homepage.getHomepageMenuTplInfo', params, function(data){
        jQuery('#menu_zone_info').html(data.tpl).css('position','absolute').css("left",e.layerX).css("top",e.layerY).css('display','block');
    });
}

function homepageModifyNode(node_srl,e){
    jQuery('#menu_zone_info').html('');
    jQuery("#tree_"+node_srl+" > span").click();

    var params ={
            "parent_srl":0
            ,"menu_item_srl":node_srl
			,"mode":"update"
            };

    jQuery.exec_json('homepage.getHomepageMenuTplInfo', params, function(data){
        jQuery('#menu_zone_info').html(data.tpl).css('position','absolute').css("left",e.layerX).css("top",e.layerY).css('display','block');
    });
}

function homepageDeleteMenu(node_srl) {
    if(confirm(lang_confirm_delete)){
        jQuery('#menuItem').css("display",'none');
        var fo_obj = jQuery('#menu_item_form').get(0);
        fo_obj.menu_item_srl.value = node_srl;
        procFilter(fo_obj, delete_menu_item);
    }
}
/* 각 메뉴의 버튼 이미지 등록 */
function doHomepageMenuUploadButton(obj) {
	// 이미지인지 체크
	if(!/\.(gif|jpg|jpeg|png)$/i.test(obj.value)) return alert(alertImageOnly);

	var fo_obj = jQuery("#fo_menu")[0];
	fo_obj.act.value = "procHomepageMenuUploadButton";
	fo_obj.target.value = obj.name;
	fo_obj.submit();
	fo_obj.act.value = "";
	fo_obj.target.value = "";
}

/* 메뉴 이미지 업로드 후처리 */
function completeMenuUploadButton(target, filename) {
	var column_name = target.replace(/^menu_/,'');
	var fo_obj = jQuery('#fo_menu')[0];
	var zone_obj = jQuery('#'+target+'_zone');
	var img_obj = jQuery('#'+target+'_img');

	fo_obj[column_name].value = filename;

	var img = new Image();
	img.src = filename;
	img_obj.attr('src', img.src);
	zone_obj.show();
}

/* 업로드된 메뉴 이미지 삭제 */
function doDeleteButton(target) {
	var fo_obj = jQuery("#fo_menu")[0];

	var col_name = target.replace(/^menu_/,'');

	var params = new Array();
	params['target'] = target;
	params['menu_srl'] = fo_obj.menu_srl.value;
	params['menu_item_srl'] = fo_obj.menu_item_srl.value;
	params['filename'] = fo_obj[col_name].value;

	var response_tags = new Array('error','message', 'target');

	exec_xml('menu','procMenuAdminDeleteButton', params, completeDeleteButton, response_tags);
}

/* 메뉴 이미지 삭제 후처리 */
function completeDeleteButton(ret_obj, response_tags) {
	var target = ret_obj['target'];
	var column_name = target.replace(/^menu_/,'');

	jQuery('#fo_menu')[0][column_name].value = '';
	jQuery('#'+target+'_img').attr('src', '');
	jQuery('#'+target+'_zone').hide();
}
