function doDeleteNews(news_srl) {
    if(typeof(news_srl)=='undefined'||!news_srl) return;
    exec_xml('project','procProjectDeleteNews',{news_srl:news_srl},function(){location.reload();});
}

function doDeleteOffer(offer_srl) {
    if(typeof(offer_srl)=='undefined'||!offer_srl) return;
    exec_xml('project','procProjectDeleteOffer',{offer_srl:offer_srl},function(){location.reload();});
}

function doSiteSignUp() {
    exec_xml('member','procModuleSiteSignUp', new Array(), function() { location.reload(); } );
}

function doSiteLeave(leave_msg) {
    if(!confirm(leave_msg)) return;
    exec_xml('member','procModuleSiteLeave', new Array(), function() { location.reload(); } );
} 


function completeReserveProject(ret_obj, response_tags, callback_func_args, fo_obj) {
    var reserve_srl = ret_obj['reserve_srl'];
    fo_obj.act.value = 'procProjectReserveImages';
    fo_obj.reserve_srl.value = reserve_srl;
    fo_obj.submit();
}

