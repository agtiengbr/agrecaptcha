$(document).ready(function() {
    if (typeof agrecaptcha === 'undefined' || !agrecaptcha.data || !agrecaptcha.data.tplRecaptcha) {
        return;
    }

    var tpl = agrecaptcha.data.tplRecaptcha;
    var customerForm = $("#customer-form");
    var contactForm = $("form[action*='/contato'], form[action*='controller=contact']").first();
    var passwordForm = $("form.forgotten-password, form[action*='password']").first();

    if (customerForm.length && !customerForm.find('.g-recaptcha').length) {
        customerForm.find('footer, .form-footer').first().before(tpl);
    }

    if (contactForm.length && !contactForm.find('.g-recaptcha').length) {
        contactForm.find('footer, .agti-contact-form__footer').first().before(tpl);
    }

    if (passwordForm.length && !passwordForm.find('.g-recaptcha').length) {
        var passwordFields = passwordForm.find('.center-email-fields').first();

        passwordFields.after(tpl);

        var passwordCaptcha = passwordFields.nextAll('.g-recaptcha').first();
        if (passwordCaptcha.length) {
            passwordCaptcha.wrap('<div class="agrecaptcha-password-captcha"></div>');

            var passwordActions = $('<div class="agrecaptcha-password-actions"></div>');
            passwordFields.find('button[name="submit"]').appendTo(passwordActions);
            passwordCaptcha.parent().after(passwordActions);
        }
    }
});
