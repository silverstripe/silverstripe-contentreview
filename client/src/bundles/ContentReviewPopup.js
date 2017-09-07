window.jQuery.entwine('ss', ($) => {
  $('.review-notes input[name="action_savereview"]').entwine({
    /**
     * Close the review popup when the form is submitted
     */
    onclick() {
      this._super();
      $('.contentreview-tab .nav-link').trigger('click');
    },
  });
});
