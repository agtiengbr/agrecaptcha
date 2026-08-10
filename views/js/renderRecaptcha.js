$(document).ready(function() {
    if (typeof agrecaptcha === 'undefined' || !agrecaptcha.data || !agrecaptcha.data.tplRecaptcha) {
        return;
    }

    var tpl = agrecaptcha.data.tplRecaptcha;
    var customerForm = $("#customer-form");
    var contactForm = $("form[action*='/contato'], form[action*='controller=contact']").first();

    if (customerForm.length && !customerForm.find('.g-recaptcha').length) {
        customerForm.find('footer, .form-footer').first().before(tpl);
    }

    if (contactForm.length && !contactForm.find('.g-recaptcha').length) {
        contactForm.find('footer, .agti-contact-form__footer').first().before(tpl);
    }
});
