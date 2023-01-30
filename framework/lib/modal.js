"use strict";
jQuery( function ( $ ) {
	
	/**
	 * Attaches the event handler for .cpwebservice_iframe_modal.click
	 */
	$( '#cpwebservice_shipping_info').on('click', '.cpwebservice_iframe_modal_btn', function ( e ) {
		e.preventDefault();

		// Retrieve the URL for th iframe stored in data-content-url created in Plugin::metabox_content
		var url = this.href ,
			$dialogHTML = $('<div tabindex="0" id="cpwebservice_iframe_modal_dialog" role="dialog"><div class="cpwebservice_iframe_modal" ><a role="button" class="cpwebservice_iframe_modal-close" href="#" title="Close"><span class="cpwebservice_iframe_modal-icon cpwebservice_ir">Close</span></a><div class="cpwebservice_iframe_modal-content"><iframe id="cpwebservice_iframe_modal-frame" src="" scrolling="no" frameborder="0" allowtransparency="true"></iframe></div></div><div class="cpwebservice_iframe_modal-backdrop" role="presentation"></div></div>' );

		if( typeof window.cpwebservice_iframe_modal_l10n === 'object' ) {
			$dialogHTML.find( '.cpwebservice_iframe_modal-close' ).attr( 'title' , cpwebservice_iframe_modal_l10n.close_label ) ;
			$dialogHTML.find( '.cpwebservice_iframe_modal-icon' ).text( cpwebservice_iframe_modal_l10n.close_label ) ;
		}
		// Sets the URL of the iframe
		$dialogHTML.find('#cpwebservice_iframe_modal-frame' ).attr('src' , url );

		// Attach the close button event handler.
		$dialogHTML.find( '.cpwebservice_iframe_modal-close' ).on( "click" , window.cpwebservice_iframe_modal_close_handler ) ;

		// When the user shifts focus (typically through pressing the tab key ).
		// If the new focus target is not a child of the modal or the modal itself,
		// set the focus on the modal -- thus resetting the tab order.
		$( document ).on( "focusin" , function( e ) {
			var $element = $( '#cpwebservice_iframe_modal_dialog' );
			if ( $element[0] !== e.target && !$element.has( e.target ).length ) {
				$element.focus();
			}
		} ) ;
		// Set overflow to hidden on the body, preventing the user from scrolling the
		// disabled content and append the dialog to the body.
		$( "body" ).css( { "overflow": "hidden" } ).append( $dialogHTML );
		
		return false;
	} );

	/**
	 *  Global Modal.close method.
	 *  We must expose the method globally so that the iframe can access the method, removing
	 *  event-handlers added during Modal.open
	 * @param e jQuery-Normalized event object
	 */
	window.cpwebservice_iframe_modal_close_handler = function( e ){
		e.preventDefault();
		$( document ).off( "focusin" ) ;
		$( "body" ).css( { "overflow": "auto" } );
		$( ".cpwebservice_iframe_modal-close" ).off( "click" );
		$( "#cpwebservice_iframe_modal_dialog").remove( ) ;
	};
	
	

} );