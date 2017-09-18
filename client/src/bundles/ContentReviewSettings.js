import jQuery from 'jquery';

jQuery.entwine('ss', ($) => {
  /**
   * Class: .cms-edit-form #Form_EditForm_ContentReviewType_Holder
   *
   * Toggle display of group dropdown in "access" tab,
   * based on selection of radiobuttons.
   */
  $('.cms-edit-form #Form_EditForm_ContentReviewType_Holder').entwine({
    // Constructor: onmatch
    onmatch() {
      const self = this;
      this.find('.optionset :input').bind('change', (e) => {
        self.show_option(e.target.value);
      });

      // initial state
      const currentVal = this.find('input[name=ContentReviewType]:checked').val();
      this.show_option(currentVal);
      this._super();
    },

    onunmatch() {
      return this._super();
    },

    show_option(value) {
      if (value === 'Custom') {
        this._custom();
      } else if (value === 'Inherit') {
        this._inherited();
      } else {
        this._disabled();
      }
    },

    _custom() {
      $('.review-settings').show();
      $('.field.custom-setting').show();
    },

    _inherited() {
      $('.review-settings').show();
      $('.field.custom-setting').hide();
    },

    _disabled() {
      $('.review-settings').hide();
    },
  });
});
