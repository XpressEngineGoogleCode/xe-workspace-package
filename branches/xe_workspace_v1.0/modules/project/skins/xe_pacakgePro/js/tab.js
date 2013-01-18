jQuery(function($){
	var tab = $('ul.pxeGnb');
	
	function onselecttab(){
		var t = $(this);
		var myclass = [];
		
		t.parents().filter('li').each(function(){
			myclass.push( $(this).attr('class') );
		});
		
		myclass = myclass.join(' ');
		if (!tab.hasClass(myclass)) tab.attr('class','pxeGnb').addClass(myclass);
	}
	
	tab.find('>li>a').click(onselecttab).focus(onselecttab);
});
