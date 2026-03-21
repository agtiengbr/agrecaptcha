<?php

class CustomerForm extends CustomerFormCore
{
    public function validate()
    {
        $emailField = $this->getField('email');
        $id_customer = Customer::customerExists($emailField->getValue(), true, true);
        $customer = $this->getCustomer();
        if ($id_customer && $id_customer != $customer->id) {
            $emailField->addError($this->translator->trans(
                'The email is already used, please choose another one or sign in',
                array(),
                'Shop.Notifications.Error'
            ));
        }

        // hook to recaptcha
        $hookResult = array_reduce(
            Hook::exec('actionValidateFromCustomer', array(), null, true),
            function ($carry, $item) {
                return $carry && $item;
            },
            true
        );

        // check birthdayField against null case is mandatory.
        $birthdayField = $this->getField('birthday');
        if (!empty($birthdayField) &&
            !empty($birthdayField->getValue()) &&
            Validate::isBirthDate($birthdayField->getValue(), Context::getContext()->language->date_format_lite)
        ) {
            $dateBuilt = DateTime::createFromFormat(
                Context::getContext()->language->date_format_lite,
                $birthdayField->getValue()
            );
            $birthdayField->setValue($dateBuilt->format('Y-m-d'));
        }
        $this->validateFieldsLengths();
        $this->validateByModules();

        return parent::validate() && $hookResult;
    }

    /**
     * This function call the hook validateCustomerFormFields of every modules
     * which added one or several fields to the customer registration form.
     *
     * Note: they won't get all the fields from the form, but only the one
     * they added.
     */
    private function validateByModules()
    {
        $formFieldsAssociated = array();
        // Group FormField instances by module name
        foreach ($this->formFields as $formField) {
            if (!empty($formField->moduleName)) {
                $formFieldsAssociated[$formField->moduleName][] = $formField;
            }
        }
        // Because of security reasons (i.e password), we don't send all
        // the values to the module but only the ones it created
        foreach ($formFieldsAssociated as $moduleName => $formFields) {
            if ($moduleId = Module::getModuleIdByName($moduleName)) {
                // ToDo : replace Hook::exec with HookFinder, because we expect a specific class here
                $validatedCustomerFormFields = Hook::exec('validateCustomerFormFields', array('fields' => $formFields), $moduleId, true);

                if (is_array($validatedCustomerFormFields)) {
                    array_merge($this->formFields, $validatedCustomerFormFields);
                }
            }
        }
    }
}