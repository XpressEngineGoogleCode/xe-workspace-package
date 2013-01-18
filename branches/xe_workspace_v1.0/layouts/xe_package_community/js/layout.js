jQuery(document).ready(function($) {
    var lang = $('#header .selectLang');
    var lang_lst = $('#header .selectLang .lst')
    var trigger = $('#header .field');
    trigger.click(function(){
        if(!lang_lst.hasClass('on')){
            lang_lst.addClass('on');
        }else if(lang_lst.hasClass('on')){
            lang_lst.removeClass('on');
        }
    })

    //for IE Placeholder
    jQuery('input, textarea').placeholder();

});