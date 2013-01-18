jQuery(function($){
	$('.dFooter button').click(function(){
		$(this).toggleClass('hidden');
        var str = $(this).parents().attr('class');
        var reg = /package_srl_(\d*)/;
        var srl = reg.exec(str);
        srl = srl[1];
		if($(this).hasClass('hidden')){
			//alert('1');
			$('.olderRelease_' + srl).css('display', 'none');
		} else {
			//alert('2');
			$('.olderRelease_' + srl).css('display', '');
		}
	});
});
