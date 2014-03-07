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
				var self = this;
				this.find('.optionset :input').bind('change', function(e) {
					self.show_option(e.target.value);
				});
		
				// initial state
				var currentVal = this.find('input[name=' + this.attr('id') + ']:checked').val();
				this.show_option(currentVal);
				this._super();
			},
			onunmatch: function() {
				return this._super();
			},
			
			show_option: function(value) {
				if(value === 'Custom') {
					this._custom();
				} else if(value === 'Inherit') {
					this._inherited();
				} else {
					this._disabled();
				}
			},
			
			_custom: function() {
				$('.custom-settings').show();
				$('.inherited-settings').hide();	
			}, 
			_inherited: function() {
				$('.inherited-settings').show();	
				$('.custom-settings').hide();
			},
			_disabled: function() {
				$('.inherited-settings').hide();	
				$('.custom-settings').hide();
			}
		});	
		
	});
});