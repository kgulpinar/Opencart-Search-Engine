<?php

class ControllerExtensionModuleIsearch extends Controller {
    private $version = '5.0.2';
    private $mid = 'I7AUYGYLYN';
    private $iid = '32';
    private $error = array();

    public function index() {
        $this->load->language('extension/module/isearch');

        $this->load->model('extension/module/isearch');
        $this->load->model('setting/setting');
        $this->load->model('setting/module');

        if (isset($this->request->get['module_id'])) {
            $this->module_id = (int)$this->request->get['module_id'];

            $this->getEngine();
        } else {
            $this->getDashboard();
        }
    }

    public function create() {
        $this->load->language('extension/module/isearch');

        $this->load->model('localisation/language');

        if ($error = $this->validate()) {
            $this->session->data['error'] = implode(' ', $error);
        } else {
            $this->load->model('setting/module');

            $default_text = array();

            foreach ($this->model_localisation_language->getLanguages() as $language) {
                $default_text[$language['language_id']] = array(
                    'heading_suggestion' => $this->language->get('text_default_heading_suggestion'),
                    'heading_results' => $this->language->get('text_default_heading_results'),
                    'loading' => $this->language->get('text_default_loading'),
                    'more' => $this->language->get('text_default_more'),
                    'nothing' => $this->language->get('text_default_nothing')
                );
            }

            $default_data = array(
                'name' => $this->language->get('text_new'),
                'search_in' => array(
                    'name' => true,
                    'model' => true,
                    'meta_description' => true,
                    'meta_keyword' => true,
                    'meta_title' => true,
                    'sku' => true,
                    'tag' => true
                ),
                'language' => 'single',
                'store' => array(
                    0 => true
                ),
                'text' => $default_text,
                'strictness' => 'moderate',
                'instant' => 'ajax',
                'sort' => 'match_name',
                'standard' => true,
                'status' => false,
                'singularization' => false,
                'spell' => array(
                    array(
                        'search' => '/\s+and\s+/i',
                        'replace' => ' '
                    ),
                    array(
                        'search' => '/\s+or\s+/i',
                        'replace' => ' '
                    ),
                    array(
                        'search' => 'cnema',
                        'replace' => 'cinema'
                    )
                ),
                'selector' => $this->language->get('placeholder_selector'),
                'width' => 'fixed',
                'width_value' => $this->language->get('placeholder_width_value'),
                'width_unit' => 'px',
                'height' => 'auto',
                'height_value' => $this->language->get('placeholder_height_value'),
                'height_unit' => 'px',
                'highlight' => true,
                'highlight_value' => $this->language->get('placeholder_highlight_value'),
                'product_limit' => $this->language->get('placeholder_product_limit'),
                'suggestion' => true,
                'suggestion_limit' => $this->language->get('placeholder_suggestion_limit'),
                'image' => true,
                'image_width' => $this->language->get('placeholder_image_width'),
                'image_height' => $this->language->get('placeholder_image_height'),
                'model' => false,
                'price' => true,
                'css' => ''
            );

            $this->model_setting_module->addModule('isearch', $default_data);

            $this->session->data['success'] = $this->language->get('text_success_create');
        }

        $this->response->redirect($this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function delete() {
        $this->load->language('extension/module/isearch');

        if ($error = $this->validate()) {
            $this->session->data['error'] = implode(' ', $error);
        } else {
            $this->load->model('setting/module');

            $this->model_setting_module->deleteModule($this->request->get['module_id']);

            $this->session->data['success'] = $this->language->get('text_success_delete');
        }

        $this->response->redirect($this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function suggestion_clear() {
        $this->load->language('extension/module/isearch');

        if ($error = $this->validate()) {
            $this->session->data['error'] = implode(' ', $error);
        } else {
            $this->load->model('extension/module/isearch');

            $this->model_extension_module_isearch->clearSuggestions($this->request->get['module_id']);

            $this->session->data['success'] = $this->language->get('text_success_suggestion_clear');
        }

        $this->response->redirect($this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true));
    }

    public function help() {
        $this->load->language('extension/module/isearch');

        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            if ($error = $this->validate()) {
                $this->session->data['error'] = implode(' ', $error);
            } else {
                $license_settings = array();

                if (!empty($this->request->post['OaXRyb1BhY2sgLSBDb21'])) {
                    $license_settings['module_isearch_licensed_on'] = $this->request->post['OaXRyb1BhY2sgLSBDb21'];
                }
                            
                if (!empty($this->request->post['cHRpbWl6YXRpb24ef4fe'])) {
                    $license_settings['module_isearch_license'] = json_decode(base64_decode($this->request->post['cHRpbWl6YXRpb24ef4fe']), true);
                }

                $this->model_setting_setting->editSetting('module_isearch', array_merge($this->model_setting_setting->getSetting('module_isearch'), $license_settings));

                $this->session->data['success'] = $this->language->get('text_license_success');
            }

            $this->response->redirect($this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('button_help'),
            'href' => $this->url->link('extension/module/isearch/help', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['cancel'] = $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['heading_help'] = sprintf($this->language->get('heading_help'), $this->language->get('heading_title') . ' ' . $this->version);
        
        $setting = $this->model_setting_setting->getSetting('module_isearch');

        $data['ticket_open'] = "http://isenselabses.com/tickets/open/" . base64_encode('Support Request') . '/' . base64_encode($this->iid) . '/' . base64_encode($this->request->server['SERVER_NAME']);

        if (!empty($setting['module_isearch_licensed_on']) && !empty($setting['module_isearch_license'])) {
            $data['licenced'] = true;
            $data['domains'] = $setting['module_isearch_license']['licenseDomainsUsed'];
            $data['customer'] = $setting['module_isearch_license']['customerName'];
            $data['license_encoded'] = base64_encode(json_encode($setting['module_isearch_license']));
            $data['license_expiry_date'] = date($this->language->get('date_format_short'), strtotime($setting['module_isearch_license']['licenseExpireDate']));
        } else {
            $data['licenced'] = false;
            $data['now'] = time();
            $data['mid'] = $this->mid;
        }
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/isearch/help', $data));
    }

    public function install() {
        if ($this->user->hasPermission('modify', 'extension/extension/module')) {
            $this->load->model('extension/module/isearch');

            $this->model_extension_module_isearch->createTables();
            $this->model_extension_module_isearch->createEvents();
            $this->model_extension_module_isearch->createIndexes();
        }
    }

    public function uninstall() {
        if ($this->user->hasPermission('modify', 'extension/extension/module')) {
            $this->load->model('extension/module/isearch');

            $this->model_extension_module_isearch->dropTables();
            $this->model_extension_module_isearch->deleteEvents();
            $this->model_extension_module_isearch->dropIndexes();
        }
    }

    protected function getEngine() {
        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/delete-button.js');
        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/persist-tabs.js');
        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/dropdown-select.js');
        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/dimension-container.js');
        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/bootstrap-colorpicker.min.js');
        $this->document->addStyle('view/stylesheet/vendor/isenselabs/isearch/bootstrap-colorpicker.min.css');
        $this->document->addStyle('view/stylesheet/vendor/isenselabs/isearch/stylesheet.css');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateEngine()) {
            $this->model_setting_module->editModule($this->module_id, $this->request->post);

            $this->cache->delete('product');

            $success = $this->language->get('text_engine_success');
        } else if (isset($this->session->data['success'])) {
            $success = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $success = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->getSettingValue('name', true),
            'href' => $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true)
        );

        $help = $this->url->link('extension/module/isearch/help', 'user_token=' . $this->session->data['user_token'], true);

        $data['help'] = $help;

        $setting = $this->model_setting_setting->getSetting('module_isearch');

        $data['success'] = $success;

        $data['error'] = '';
        $is_validation_error = false;

        if (isset($this->session->data['error'])) {
            $data['error'] = $this->session->data['error'];

            unset($this->session->data['error']);
        } else if (isset($this->error['warning'])) {
            $data['error'] = $this->error['warning'];
            $is_validation_error = true;
        }

        $data['error_name'] = $this->getErrorValue('name');
        $data['error_language'] = $this->getErrorValue('language');
        $data['error_store'] = $this->getErrorValue('store');
        $data['error_search_in'] = $this->getErrorValue('search_in');

        $data['css'] = $this->getSettingValue('css');
        $data['exclude'] = json_encode($this->getExcludes());
        $data['height'] = $this->getSettingValue('height');
        $data['height_unit'] = $this->getSettingValue('height_unit');
        $data['height_value'] = $this->getSettingValue('height_value');
        $data['highlight'] = $this->getSettingValue('highlight');
        $data['highlight_value'] = $this->getSettingValue('highlight_value');
        $data['image'] = $this->getSettingValue('image');
        $data['image_height'] = $this->getSettingValue('image_height');
        $data['image_width'] = $this->getSettingValue('image_width');
        $data['instant'] = $this->getSettingValue('instant');
        $data['language'] = $this->getSettingValue('language');
        $data['model'] = $this->getSettingValue('model');
        $data['name'] = $this->getSettingValue('name');
        $data['price'] = $this->getSettingValue('price');
        $data['product_limit'] = $this->getSettingValue('product_limit');
        $data['search_in'] = $this->getSettingValue('search_in');
        $data['selector'] = $this->getSettingValue('selector');
        $data['singularization'] = $this->getSettingValue('singularization');
        $data['sort'] = $this->getSettingValue('sort');
        $data['spell'] = json_encode($this->getSettingValue('spell'));
        $data['standard'] = $this->getSettingValue('standard');
        $data['status'] = $this->getSettingValue('status');
        $data['store'] = $this->getSettingValue('store');
        $data['strictness'] = $this->getSettingValue('strictness');
        $data['suggestion'] = $this->getSettingValue('suggestion');
        $data['suggestion_limit'] = $this->getSettingValue('suggestion_limit');
        $data['text'] = $this->getSettingValue('text');
        $data['width'] = $this->getSettingValue('width');
        $data['width_unit'] = $this->getSettingValue('width_unit');
        $data['width_value'] = $this->getSettingValue('width_value');

        $data['prepare_browser'] = !$is_validation_error && $data['instant'] == 'browser';

        $url = new Url(HTTP_CATALOG, HTTPS_CATALOG);
        $data['url_prepare_browser'] = html_entity_decode($url->link('extension/module/isearch/browser', '', true));

        $data['heading_module'] = $this->language->get('heading_title') . ' ' . $this->version . ' - ' . $this->getSettingValue('name', true);
        $data['cancel'] = $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['action'] = $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true);
        $data['suggestion_clear'] = $this->url->link('extension/module/isearch/suggestion_clear', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true);

        $data['fields'] = $this->getFields();
        $data['sort_matches'] = $this->getSortMatches();
        $data['sort_lengths'] = $this->getSortLengths();

        $data['user_token'] = $this->session->data['user_token'];

        $data['stores'] = $this->model_extension_module_isearch->getStores();

        $this->load->model("localisation/language");
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model("localisation/stock_status");
        $data['stock_statuses'] = $this->model_localisation_stock_status->getStockStatuses();

        $tabs = array();
        $tabs['setting'] = 'fa fa-wrench';
        $tabs['advanced'] = 'fa fa-sliders';
        $tabs['localisation'] = 'fa fa-flag';
        $tabs['design'] = 'fa fa-paint-brush';

        $data['tabs'] = array();

        foreach ($tabs as $tab => $icon) {
            $data['tabs'][$tab] = array(
                'html' => $this->load->view('extension/module/isearch/engine/' . $tab, $data),
                'title' => $this->language->get('tab_' . $tab),
                'icon' => $icon
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/isearch/engine', $data));
    }

    protected function iterateFields($callback) {
        $result = array();

        $this->load->library('vendor/isenselabs/isearch/field');

        foreach ($this->field->getFields() as $key => $field) {
            $callback($result, $key, $field);
        }

        return $result;
    }

    protected function getFields() {
        return $this->iterateFields(function(&$result, $key) {
            $result[$key] = array(
                'name' => $this->language->get('field_name_' . $key)
            );
        });
    }

    protected function getSortMatches() {
        return $this->iterateFields(function(&$result, $key) {
            $result['match_' . $key] = sprintf($this->language->get('text_sort_match'), $this->language->get('field_name_' . $key));
        });
    }

    protected function getSortLengths() {
        return $this->iterateFields(function(&$result, $key) {
            $result['length_' . $key] = sprintf($this->language->get('text_sort_length'), $this->language->get('field_name_' . $key));
        });
    }

    protected function getExcludes() {
        $data = $this->getSettingValue('exclude');

        if (is_array($data)) {
            $this->load->model('catalog/product');
            $this->load->model('catalog/category');

            foreach($data as &$exclude) {
                if (!isset($exclude['data'])) {
                    continue;
                }

                switch ($exclude['type']) {
                    case 'category' : {
                        foreach ($exclude['data'] as &$category) {
                            $category_info = $this->model_catalog_category->getCategory($category['category_id']);
                            
                            if ($category_info['path']) {
                                $category['name'] = $category_info['path'] . ' > ' . $category_info['name'];
                            } else {
                                $category['name'] = $category_info['name'];
                            }
                        }
                    } break;
                    case 'product' : {
                        foreach ($exclude['data'] as &$product) {
                            $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                            $product['name'] = $product_info['name'];
                        }
                    } break;
                }
            }
        }

        return $data;
    }

    protected function getErrorValue($key) {
        if (isset($this->error[$key])) {
            return $this->error[$key];
        }

        return false;
    }

    protected function getSettingValue($key, $ignore_post = false) {
        $this->load->model('setting/module');

        $module_info = $this->model_setting_module->getModule($this->module_id);

        if (isset($this->request->post[$key]) && !$ignore_post) {
            return $this->request->post[$key];
        } else if (isset($module_info[$key])) {
            return $module_info[$key];
        } else if (isset($module_info['setting'][$key])) {
            return $module_info['setting'][$key];
        }
    }

    protected function getDashboard() {
        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addScript('view/javascript/vendor/isenselabs/isearch/delete-button.js');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'], true)
        );

        $help = $this->url->link('extension/module/isearch/help', 'user_token=' . $this->session->data['user_token'], true);
        $data['help'] = $help;

        $setting = $this->model_setting_setting->getSetting('module_isearch');

        $data['error'] = '';

        $data['success'] = '';

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            
            unset($this->session->data['success']);
        }

        $data['heading_dashboard'] = sprintf($this->language->get('heading_dashboard'), $this->language->get('heading_title') . ' ' . $this->version);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $create = $this->url->link('extension/module/isearch/create', 'user_token=' . $this->session->data['user_token'], true);

        $data['create'] = $create;
        $data['no_result'] = sprintf($this->language->get('text_no_results'), $create);

        $data['modules'] = array();

        foreach ($this->model_setting_module->getModulesByCode('isearch') as $module) {
            $module_info = json_decode($module['setting'], true);

            $data['modules'][] = array(
                'name' => $module['name'],
                'status' => $module_info['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'stores' => $this->model_extension_module_isearch->getEngineStores($module_info['store']),
                'edit' => $this->url->link('extension/module/isearch', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module['module_id'], true),
                'delete' => $this->url->link('extension/module/isearch/delete', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module['module_id'], true)
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/isearch/dashboard', $data));
    }

    protected function validate() {
        $error = array();

        if (!$this->user->hasPermission('modify', 'extension/module/isearch')) {
            $error['warning'] = $this->language->get('error_permission');
        }

        return $error;
    }

    protected function validateEngine() {
        $this->error = $this->validate();

        if ((utf8_strlen($this->request->post['name']) < 1) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        if (empty($this->request->post['store'])) {
            $this->error['store'] = $this->language->get('error_store');
        }

        if (empty($this->request->post['search_in'])) {
            $this->error['search_in'] = $this->language->get('error_search_in');
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }
}