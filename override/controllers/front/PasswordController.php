<?php

class PasswordController extends PasswordControllerCore
{

    /*
    * module: agrecaptcha
    * date: 2023-04-27 13:52:58
    * version: 1.0.3
    */

    public function postProcess()
    {
        $this->setTemplate('customer/password-email');

        if (Tools::isSubmit('email')) {
            $hookResult = array_reduce(
                Hook::exec('actionValidateSendRenewPasswordLink', array(), null, true),
                function ($carry, $item) {
                    return $carry && $item;
                },
                true
            );
            if($hookResult){
                $this->sendRenewPasswordLink();
            }
        } elseif (Tools::getValue('token') && ($id_customer = (int) Tools::getValue('id_customer'))) {
            $this->changePassword();
        } elseif (Tools::getValue('token') || Tools::getValue('id_customer')) {
            $this->errors[] = $this->trans('We cannot regenerate your password with the data you\'ve submitted', [], 'Shop.Notifications.Error');
        }
    }
}

