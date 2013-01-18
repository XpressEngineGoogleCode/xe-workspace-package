/**
 * @file   modules/board/skin/xe_board_File/js/fileAct.js
 * @author NHN (developers@xpressengine.com)
 * @brief  recruit   javascript
 **/

function doDeleteGroup(divName)
{
    var dSrl = [];
    var cartBoxes = jQuery('.cartCheckBox');
    for(var i in cartBoxes)
    {
        if(!cartBoxes[i].checked) continue;
        dSrl[dSrl.length] = cartBoxes[i].value;
    }
    if(!dSrl.length) return;
    dSrl = dSrl.join(',');

    divName = divName || 'deleteForm';
    showDelete(dSrl, divName);
}

function addDetail(ret_obj, response_tags)
{
    var error = ret_obj['error'];
    var content = ret_obj['content'];
    var srl = ret_obj['srl'];

    if(ret_obj['message']!='success') alert(ret_obj['message']);

    jQuery('.detailRow').css('display','none');
    var jobTd = jQuery(".detail_" + srl);
    jobTd.children().html(content);
    jobTd.css('display','');
}

function completeDel(ret_obj, response_tags)
{
    var error = ret_obj['error'];
    var content = ret_obj['content'];
    var srl = ret_obj['srl'];

    if(ret_obj['error']!=0)
    {
        alert(ret_obj['message']);
        return;
    }
    location.reload();
}

function delFile()
{
    var act = 'procBoardDeleteFile';
    var params = {};
    var srl = jQuery('#deleteFormSrl').val();
    if(!srl) return;
    params['file_srl'] = srl;
    params['act'] = act;
    exec_xml('board', act, params, completeDel,['error','message','file_srl']);
}

function showDelete(srl, divName)
{
    divName = divName || 'deleteForm';
    jQuery('.'+divName).css('display','block');
    jQuery('#deleteFormSrl').val(srl);
}

function cancelDelete()
{
    jQuery('.deleteForm').css('display','none');
    jQuery('.deleteCommentForm').css('display','none');
}