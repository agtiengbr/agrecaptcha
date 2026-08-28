<?php
require_once _PS_MODULE_DIR_ . 'agcliente/lib/AgModule.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class BaseAgRecaptcha extends AgModule implements WidgetInterface{

    protected $hooks = [
        'displayBeforeContactFormSubmitButton',
        'displayHeader',
        'ActionBeforeSubmitAccount',
        'displayCustomerAccountForm',
        'actionDispatcher',
        'actionValidateFromCustomer',
        'actionValidateSendRenewPasswordLink'
    ];

    public function __construct()
    {
        $this->name                   = 'agrecaptcha';
        $this->version                = '1.1.3';
        $this->bootstrap              = true;
        $this->author                 = 'AGTI';
        $this->need_instance          = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.99');
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
            !$this->registerHook('actionDispatcher')||
            !$this->registerHook('ActionBeforeSubmitAccount')||
            !$this->registerHook('displayCustomerAccountForm')||
            !$this->registerHook('actionValidateFromCustomer')||
            !$this->registerHook('actionValidateSendRenewPasswordLink')
            
        ) {
            return false;
        }

        $this->addGroupPermissions();

        return true;
    }

    private function addGroupPermissions()
    {
        $shopId = (int) $this->context->shop->id;
        $groups = Db::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'group');

        foreach ($groups as $g) {
            $idGroup = (int) $g['id_group'];
            $exists = (bool) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'module_group '
                . 'WHERE id_module = ' . (int) $this->id
                . ' AND id_shop = ' . $shopId
                . ' AND id_group = ' . $idGroup
            );
            if (!$exists) {
                Db::getInstance()->execute(
                    'INSERT INTO ' . _DB_PREFIX_ . 'module_group (id_module, id_shop, id_group) '
                    . 'VALUES (' . (int) $this->id . ', ' . $shopId . ', ' . $idGroup . ')'
                );
            }
        }
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->getConnectTimeout());
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->getRequestTimeout());

        $result = curl_exec($ch);

        return json_decode($result);
    }

    private function getConnectTimeout()
    {
        $timeout = (int) Configuration::get('RECAPTCHA_CONNECT_TIMEOUT');

        return $timeout > 0 ? $timeout : 5;
    }

    private function getRequestTimeout()
    {
        $timeout = (int) Configuration::get('RECAPTCHA_TIMEOUT');

        return $timeout > 0 ? $timeout : 10;
    }

    


    public function getContent(){

        if (Tools::isSubmit('recaptcha-config')) {
            Configuration::updateValue('RECAPTCHA_PUBLIC_KEY', Tools::getValue('RECAPTCHA_PUBLIC_KEY'));
            Configuration::updateValue('RECAPTCHA_PRIVATE_KEY', Tools::getValue('RECAPTCHA_PRIVATE_KEY'));
            Configuration::updateValue('RECAPTCHA_CREATE_CUSTOMER', Tools::getValue('RECAPTCHA_CREATE_CUSTOMER'));
        }

        if (Tools::isSubmit('recaptcha-advanced-config')) {
            Configuration::updateValue('RECAPTCHA_CONNECT_TIMEOUT', $this->getPostedTimeout('RECAPTCHA_CONNECT_TIMEOUT', 5));
            Configuration::updateValue('RECAPTCHA_TIMEOUT', $this->getPostedTimeout('RECAPTCHA_TIMEOUT', 10));
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

        $advancedForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configurações avançadas'),
                ],
                'description' => $this->l('Ajuste quanto tempo a loja deve aguardar o serviço de proteção do Google. Os valores são contados em segundos.'),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Tempo para iniciar a conexão'),
                        'name' => 'RECAPTCHA_CONNECT_TIMEOUT',
                        'class' => 'center',
                        'col' => 2,
                        'suffix' => $this->l('segundos'),
                        'desc' => $this->l('Tempo máximo para conseguir contato com o Google. Recomendado: 5 segundos.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tempo máximo da verificação'),
                        'name' => 'RECAPTCHA_TIMEOUT',
                        'class' => 'center',
                        'col' => 2,
                        'suffix' => $this->l('segundos'),
                        'desc' => $this->l('Tempo máximo para receber a resposta do Google. Recomendado: 10 segundos.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Salvar configurações avançadas'),
                    'name' => 'recaptcha-advanced-config',
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper->fields_value['RECAPTCHA_CONNECT_TIMEOUT'] = $this->getConnectTimeout();
        $helper->fields_value['RECAPTCHA_TIMEOUT'] = $this->getRequestTimeout();

        $generalForm = $helper->generateForm([$formConfig]);
        $advancedFormHtml = $helper->generateForm([$advancedForm]);

        return '<ul class="nav nav-tabs" role="tablist">'
            . '<li class="active"><a href="#agrecaptcha-general" role="tab" data-toggle="tab">'
            . $this->l('Configurações gerais') . '</a></li>'
            . '<li><a href="#agrecaptcha-advanced" role="tab" data-toggle="tab">'
            . $this->l('Configurações avançadas') . '</a></li>'
            . '</ul>'
            . '<div class="tab-content">'
            . '<div class="tab-pane active" id="agrecaptcha-general">' . $generalForm . '</div>'
            . '<div class="tab-pane" id="agrecaptcha-advanced">' . $advancedFormHtml . '</div>'
            . '</div>';
    }

    private function getPostedTimeout($name, $default)
    {
        $timeout = (int) Tools::getValue($name, $default);

        return $timeout > 0 ? min($timeout, 300) : $default;
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
        $this->context->controller->registerStylesheet(
            'module-agrecaptcha-css',
            'modules/' . $this->name . '/views/css/agrecaptcha.css',
            ['media' => 'all', 'priority' => 100, 'version' => $this->version]
        );
        $this->context->controller->registerJavascript(
            'module-agrecaptcha-js',
            'modules/' . $this->name . '/views/js/renderRecaptcha.js',
            ['position' => 'bottom', 'priority' => 100, 'version' => $this->version]
        );

        if ($this->context->controller->php_self === 'contact') {
            Media::addJsDef([
                'agrecaptcha' => [
                    'data' => [
                        'tplRecaptcha' => $this->renderWidget('displayContactContent'),
                    ],
                ],
            ]);
        }

        if ($this->context->controller->php_self === 'password') {
            Media::addJsDef([
                'agrecaptcha' => [
                    'data' => [
                        'tplRecaptcha' => $this->renderWidget('displayPassword'),
                    ],
                ],
            ]);
        }
    }

    public function hookActionDispatcher($params)
    {
        if (!$this->active || !Tools::isSubmit('submitMessage')) {
            return;
        }

        $controllerClass = isset($params['controller_class']) ? (string) $params['controller_class'] : '';
        if (stripos($controllerClass, 'ContactController') === false) {
            return;
        }

        $recaptcha = $this->verifyRecaptcha(Tools::getValue('g-recaptcha-response'));
        if (!$recaptcha || empty($recaptcha->success)) {
            $this->context->controller->errors[] = $this->trans(
                'Recaptcha invalido.',
                [],
                'Shop.Notifications.Error'
            );
        }
    }
}
