/**
 * @file   modules/guestbook/tpl/js/guestbook.js
 * @author NHN (developers@xpressengine.com)
 * @brief  guestbook module javascript
 **/

function deleteGuestbookItem(guestbook_item_srl,page){
    var params = new Array();
    params['guestbook_item_srl'] = guestbook_item_srl;
	
	var response_tags = new Array('error','message','page','mid');
    exec_xml('guestbook', 'procGuestbookDeleteGuestbookItem', params, completeReload, response_tags);
}

function completeReload(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];

    location.reload();
}