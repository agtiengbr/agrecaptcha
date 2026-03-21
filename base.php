<?php
require_once _PS_MODULE_DIR_ . 'agcliente/lib/AgModule.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class BaseAgRecaptcha extends AgModule implements WidgetInterface{

    protected $hooks = [
        'displayBeforeContactFormSubmitButton',
        'displayHeader',
        'ActionBeforeSubmitAccount',
        'displayCustomerAccountForm',
        'actionValidateFromCustomer',
        'actionValidateSendRenewPasswordLink'
    ];

    public function __construct()
    {
        $this->name                   = 'agrecaptcha';
        $this->version                = '1.0.6';
        $this->bootstrap              = true;
        $this->author                 = 'AGTI';
        $this->need_instance          = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '8.99');
        parent::__construct();

        $this->displayName = 'Recaptcha';
        $this->description = 'Adiciona a verificação antirobô do Google no cadastro do cliente na sua loja.';
    } 


    public function install()
    {
        if (
            !parent::install() ||
            !$this->registerHook('displayBeforeContactFormSubmitButton')||
            !$this->registerHook('displayHeader')||
            !$this->registerHook('ActionBeforeSubmitAccount')||
            !$this->registerHook('displayCustomerAccountForm')||
            !$this->registerHook('actionValidateFromCustomer')||
            !$this->registerHook('actionValidateSendRenewPasswordLink')
            
        ) {
            return false;
        }
        return true;
    }

      // hook criado para esse modulo em controllers/front/PasswordController.php
      public function hookActionValidateSendRenewPasswordLink(){
        if($this->active){
            return $this->checkRecaptcha();
        }
    }
    
    // hook criado para esse modulo em classes/form/CustomerForm.php
    public function hookActionValidateFromCustomer(){
        if($this->active){
            return $this->checkRecaptcha();
        }

    }

    // renderiza o recaptcha nos forms do carrinho e criar conta
    public function DisplayCustomerAccountForm($params){

        if (!$this->active) {
            return false;
        }


        $tplRecaptcha=$this->renderWidget();
          
        Media::addJsDef([
            'agrecaptcha' => [
                'data' => [
                    'tplRecaptcha' => $tplRecaptcha,
                ],
            ]
        ]);



        return $tplRecaptcha;
    }
    
    public function checkRecaptcha($params = ''){

        if (!$this->active) {
            return true;
        }

        if(!Configuration::get('RECAPTCHA_CREATE_CUSTOMER')){
            return true;
        }
        $recaptcha = $this->verifyRecaptcha(Tools::getValue('g-recaptcha-response'));
        if (!$recaptcha->success) {
            $this->context->controller->errors[] = $this->trans(
                'Recaptcha invalido.',
                [],
                'Shop.Notifications.Error'
            );
            return false;
        }
        return true;

    }

    
    public function hookDisplayBeforeContactFormSubmitButton($params){
        if (!$this->active) {
            return false;
        }

        $recaptcha=$this->verifyRecaptcha($params['g-recaptcha-response']);
        if (!$recaptcha->success) {
            $this->context->controller->errors[] = $this->trans(
                'Recaptcha invalido.',
                [],
                'Shop.Notifications.Error'
            );
            return;
        }
    }

    public function verifyRecaptcha($params){
        if (!$this->active) {
            return false;
        }

        $ch = curl_init();

        $secret = Configuration::get('RECAPTCHA_PRIVATE_KEY');
        $bodyReq=
        [
            'secret' => $secret,
            'response' => Tools::getValue('g-recaptcha-response')
        ];


        curl_setopt($ch, CURLOPT_URL,"https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyReq);

        $result = curl_exec($ch);

        return json_decode($result);
    }

    


    public function getContent(){

        if (Tools::isSubmit('recaptcha-config')) {
            Configuration::updateValue('RECAPTCHA_PUBLIC_KEY', Tools::getValue('RECAPTCHA_PUBLIC_KEY'));
            Configuration::updateValue('RECAPTCHA_PRIVATE_KEY', Tools::getValue('RECAPTCHA_PRIVATE_KEY'));
            Configuration::updateValue('RECAPTCHA_CREATE_CUSTOMER', Tools::getValue('RECAPTCHA_CREATE_CUSTOMER'));
        }
       
        $formConfig = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cofigurações Recaptcha'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Chave Publica'),
                        'class' => 'center',
                        'col' => 2,
                        'name' => 'RECAPTCHA_PUBLIC_KEY'

                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Chave Privada'),
                        'class' => 'center',
                        'col' => 2,
                        'name' => 'RECAPTCHA_PRIVATE_KEY'
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Recaptcha na criação de conta'),
                        'name' => 'RECAPTCHA_CREATE_CUSTOMER',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Admin.Global'),
                            ],
                        ]
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Salvar'),
                    'name' => 'recaptcha-config',
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value['RECAPTCHA_PUBLIC_KEY'] = Configuration::get('RECAPTCHA_PUBLIC_KEY');
        $helper->fields_value['RECAPTCHA_PRIVATE_KEY'] = Configuration::get('RECAPTCHA_PRIVATE_KEY');
        $helper->fields_value['RECAPTCHA_CREATE_CUSTOMER'] = Configuration::get('RECAPTCHA_CREATE_CUSTOMER');
        
        return $helper->generateForm([$formConfig]);
    }

    public function renderWidget($hookName =null, array $configuration = [])
    {
    
        if (!$this->active) {
            return false;
        }

        // se for no form de contato o hookName pode ser null
        if(Context::getContext()->controller->php_self == "contact" || Context::getContext()->controller->php_self == "password"){
            goto renderWidget;
        }

        // dump($this->context->controller);
        // se for null ou se não for no controlador do form do carrinho,de contato ou de autenticação não mostra o captcha
        if (!Configuration::get('RECAPTCHA_CREATE_CUSTOMER') || is_null($hookName) || (Context::getContext()->controller->php_self != "order" && Context::getContext()->controller->php_self != 'authentication' && Context::getContext()->controller->php_self != 'registration')) {
            return false;
        }
        renderWidget:

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        return $this->display(_PS_MODULE_DIR_ . $this->name,'views/templates/hook/recaptcha.tpl');
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        if (!$this->active) {
            return false;
        }

        $secret = Configuration::get('RECAPTCHA_PUBLIC_KEY');
        return [
            'secret_key'=>$secret
        ];
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCss($this->_path . 'views/css/agrecaptcha.css');        
        $this->context->controller->addJs(array(
            _PS_MODULE_DIR_ . $this->name . '/views/js/renderRecaptcha.js'
        ));
    }
}
