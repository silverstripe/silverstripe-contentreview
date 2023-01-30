/* global window */
import i18n from 'i18n';
import jQuery from 'jquery';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { loadComponent } from 'lib/Injector';

const FormBuilderModal = loadComponent('FormBuilderModal');

/**
 * "Content due for review" modal popup. See AddToCampaignForm.js in
 * silverstripe/admin for reference.
 */
jQuery.entwine('ss', ($) => {
  /**
   * Kick off a "content due for review" dialog from the CMS actions.
   */
  $('.cms-content-actions .content-review__button').entwine({
    onclick(event) {
      event.preventDefault();

      let dialog = $('#content-review__dialog-wrapper');

      if (!dialog.length) {
        dialog = $('<div id="content-review__dialog-wrapper" />');
        $('body').append(dialog);
      }

      dialog.open();

      return false;
    },
  });

  // This is required because the React version of e.preventDefault() doesn't work
  // this is to prevent PJAX request to occur when clicking a link the modal
  $('.content-review-modal .content-review-modal__nav-link').entwine({
    onclick: (e) => {
      e.preventDefault();
      const $link = $(e.target);
      window.location = $link.attr('href');
    },
  });

  /**
   * Uses reactstrap in order to replicate the bootstrap styling and JavaScript behaviour.
   */
  $('#content-review__dialog-wrapper').entwine({
    ReactRoot: null,

    onunmatch() {
      // solves errors given by ReactDOM "no matched root found" error.
      this._clearModal();
    },

    open() {
      this._renderModal(true);
    },

    close() {
      this._renderModal(false);
    },

    _renderModal(isOpen) {
      const handleHide = () => this.close();
      const handleSubmit = (...args) => this._handleSubmitModal(...args);
      const id = $('form.cms-edit-form :input[name=ID]').val();
      const sectionConfigKey = 'SilverStripe\\CMS\\Controllers\\CMSPageEditController';
      const store = window.ss.store;
      const sectionConfig = store.getState().config.sections
        .find((section) => section.name === sectionConfigKey);
      const modalSchemaUrl = `${sectionConfig.form.ReviewContentForm.schemaUrl}/${id}`;
      const title = i18n._t('ContentReview.CONTENT_DUE_FOR_REVIEW', 'Content due for review');

      let root = this.getReactRoot();
      if (!root) {
        root = createRoot(this[0]);
        this.setReactRoot(root);
      }
      root.render(
        <FormBuilderModal
          title={title}
          isOpen={isOpen}
          onSubmit={handleSubmit}
          onClosed={handleHide}
          schemaUrl={modalSchemaUrl}
          bodyClassName="modal__dialog"
          className="content-review-modal"
          responseClassBad="modal__response modal__response--error"
          responseClassGood="modal__response modal__response--good"
          identifier="ContentReview.CONTENT_DUE_FOR_REVIEW"
        />
      );
    },

    _clearModal() {
      const root = this.getReactRoot();
      if (root) {
        root.unmount();
        this.setReactRoot(null);
      }
    },

    _handleSubmitModal(data, action, submitFn) {
      // Remove the "review content" bell button so users won't do it again
      $('.content-review__button-holder').remove();

      // Handle the review submission
      return submitFn();
    },
  });
});
