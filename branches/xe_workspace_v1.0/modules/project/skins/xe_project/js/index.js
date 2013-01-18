jQuery(window).load( function() {
	var pause = true;

    function spread() {
        var stop = true;
	    jQuery('ol.slideList').find('li').each(function(i) {
		    var o = jQuery(this);
            var l = parseInt(o.css('left'),10);
            if(i*(188+10)>l) { 
               o.css('left', l+100);
               stop = false;
            }
	    });
        if(!stop) setTimeout(spread, 50);
        else {
            pause = false;
            setTimeout(slide, 1000);
        }
    }
    setTimeout(spread, 500);

    jQuery('ol.slideList').mouseover( function() { pause = true; });
    jQuery('ol.slideList').mouseout( function() { pause = false; });

	function slide() {
		if(!pause) {
			var o = jQuery('ol.slideList>li');
			var t = o.eq(0);
	        if(parseInt(t.css('left'),10)<-188-10) {
				t.appendTo('ol.slideList');
				t.css('left', parseInt(o.eq(o.length-1).css('left'),10)+188+10);
		    }
            
			o.each(function(i) {
	            var t = jQuery(this);
	            t.css('left', parseInt(t.css('left'),10)-20);
	        });
            var a = parseInt(o.eq(1).css('left'),10);
            if(a>10 && a<40) {
			    o.each(function(i) {
	                var t = jQuery(this);
	                t.css('left', parseInt(t.css('left'),10)-a+10);
	            });
                setTimeout(slide, 2000);
            } else {
                setTimeout(slide, 10);
            }
		 } else {
                setTimeout(slide, 100);
         }
	};
});
