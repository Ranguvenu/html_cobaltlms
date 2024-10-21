/**
 * Add a create new group modal to the page.
 *
 * @module     local_program
 * @class      NewCategory
 * @package
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     *
     * @param {object} args
     * / @param {String} args used to find triggers for the new group modal.
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewCat = function(args) {
        this.contextid = args.contextid;


        this.categoryid = args.categoryid;
        var self = this;
        self.init(args.selector);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    NewCat.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    NewCat.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    NewCat.prototype.init = function() {
        var self = this;
            var editid = $(this).data('value');
            if (editid) {
                self.instituteid = editid;
            }
            return Str.get_string('adnewcat', 'local_program').then(function(title) {
                // Create the modal.
                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: title,
                    body: self.getBody()
                });
            }.bind(self)).then(function(modal) {
                // Keep a reference to the modal.
                self.modal = modal;
                self.modal.show();
                // Forms are big, we want a big modal.
                self.modal.setLarge();

                // We want to reset the form every time it is opened.
                self.modal.getRoot().on(ModalEvents.hidden, function() {
                    self.modal.setBody(self.getBody());
                }.bind(this));
                // We want to hide the submit buttons every time it is opened.
                self.modal.getRoot().on(ModalEvents.shown, function() {
                    self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                }.bind(this));
                // We catch the modal save event, and use it to submit the form inside the modal.
                // Triggering a form submission will give JS validation scripts a chance to check for errors.
                self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
                // We also catch the form submit event and use it to submit the form with ajax.
                self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
                return this.modal;
            }.bind(this));
    };
    /**
     * @param {object} formdata
     * @method getBody
     * @private
     * @return {Promise}
     */
    NewCat.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {instituteid:this.instituteid, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_program', 'new_catform', this.contextid, params);
    };
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewCat.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
        document.location.reload();
    };
    /**
     * @param {object} data
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    NewCat.prototype.handleFormSubmissionFailure = function(data) {
        // Oh noes! Epic fail :(
        // Ah wait - this is normal. We need to re-display the form with errors!
        this.modal.setBody(this.getBody(data));
    };
    /**
     * Private method
     *
     * @method submitFormAjax
     * @private
     * @param {Event} e Form submission event.
     */
    NewCat.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_location_submit_catform_form',
            args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
            done: this.handleFormSubmissionResponse.bind(this, formData),
            fail: this.handleFormSubmissionFailure.bind(this, formData)
        }]);
    };
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    NewCat.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    return {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} args The CSS selector used to find nodes that will trigger this module.
         * @return {Promise}
         */
        init: function(args) {
            return new NewCat(args);
        },
        load: function(){

        }
    };
});
