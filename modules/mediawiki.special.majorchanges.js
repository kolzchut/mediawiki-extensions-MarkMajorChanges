$('.mw-tag-שינוי_מהותי_טופל').each( function( e ) {
	var $this = $(this);
	$this.find('input:checkbox').first().replaceWith( '<span class="fa fa-check" aria-hidden="true"></span><span class="sr-only">טופל</span>');
	$this.find( '.mw-tag-markers' ).hide();
});
