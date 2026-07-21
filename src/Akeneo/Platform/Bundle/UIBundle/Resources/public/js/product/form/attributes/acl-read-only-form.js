'use strict';
/**
 * Makes every product attribute field read-only when the user may VIEW attributes but not EDIT them.
 *
 * The attributes tab itself is gated by the "view attributes" ACL (see form_extensions), so it is
 * visible to viewers. This extension then reuses the existing field edit/view-mode mechanism: for each
 * field registration (event 'pim_enrich:form:field:extension:add', triggered per field in
 * product/field/field.js render), when the user lacks the edit ACL it flips the field to non-editable,
 * so the field renders in 'view' mode (see field.js getEditMode) — disabled inputs, no save.
 *
 * The edit ACL to test is configurable (config.editAclResourceId) so the same module serves products
 * and product models. The ACL is checked per field (not once in configure) so the security context is
 * guaranteed to be loaded by the time a field is added.
 *
 * @copyright 2026 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(['jquery', 'underscore', 'pim/form', 'pim/security-context'], function ($, _, BaseForm, SecurityContext) {
  return BaseForm.extend({
    /**
     * {@inheritdoc}
     */
    configure: function () {
      this.listenTo(this.getRoot(), 'pim_enrich:form:field:extension:add', this.addFieldExtension);

      return BaseForm.prototype.configure.apply(this, arguments);
    },

    /**
     * Flip a freshly-registered field to read-only when the edit ACL is not granted.
     *
     * @param {Object} event
     */
    addFieldExtension: function (event) {
      var editAclResourceId =
        (this.config && this.config.editAclResourceId) || 'pim_enrich_product_edit_attributes';

      if (event && event.field && typeof event.field.setEditable === 'function') {
        if (!SecurityContext.isGranted(editAclResourceId)) {
          event.field.setEditable(false);
        }
      }

      return this;
    },
  });
});
