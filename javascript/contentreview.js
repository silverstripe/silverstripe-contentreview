/*jslint browser: true, nomen: true,  white: true */ /*global  $, jQuery*/

jQuery(function($) {
	"use strict";
	
	$.entwine('ss', function($) {
		
		
		/**
		 * Class: .cms-edit-form #ContentReviewType
		 * 
		 * Toggle display of group dropdown in "access" tab,
		 * based on selection of radiobuttons.
		 */
		$('.cms-edit-form #ContentReviewType').entwine({
			// Constructor: onmatch
			onmatch: function() {
				// TODO Decouple
				var dropdown;
				if(this.attr('id') == 'ContentReviewType') dropdown = $('.contentReviewSettings');
		
				this.find('.optionset :input').bind('change', function(e) {
					var wrapper = $(this).closest('.middleColumn').parent('div');
					if(e.target.value == 'Custom') {
						wrapper.addClass('remove-splitter');
						dropdown['show']();
					}
					else {
						wrapper.removeClass('remove-splitter');
						dropdown['hide']();	
					}
				});
		
				// initial state
				var currentVal = this.find('input[name=' + this.attr('id') + ']:checked').val();
				dropdown[currentVal == 'Custom' ? 'show' : 'hide']();
				
				this._super();
			},
			onunmatch: function() {
				this._super();
			}
		});	
		
	});
});