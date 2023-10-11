import jQuery from 'jquery';

jQuery.entwine('ss', ($) => {
  // Hide all owner dropdowns except the one for the current subsite
  function showCorrectSubsiteIDDropdown(value) {
    const domid = `ContentReviewOwnerID${value}`;

    const ownerIDDropdowns = $('div.subsiteSpecificOwnerID');
    let i = 0;
    for (i = 0; i < ownerIDDropdowns.length; i++) {
      if (ownerIDDropdowns[i].id === domid) {
        $(ownerIDDropdowns[i]).show();
      } else {
        $(ownerIDDropdowns[i]).hide();
      }
    }
  }

  $('#Form_EditForm_SubsiteIDWithOwner').entwine({
    // Call method to show on report load
    onmatch() {
      showCorrectSubsiteIDDropdown(this.value);
    },

    // Call method to show on dropdown change
    change() {
      showCorrectSubsiteIDDropdown(this.value);
    },
  });
});
