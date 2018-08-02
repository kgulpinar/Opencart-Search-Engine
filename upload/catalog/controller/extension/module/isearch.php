<?php

class ControllerExtensionModuleIsearch extends Controller {
    public function init(&$route, &$data, &$output) {
        $this->load->model('extension/module/isearch');

        if ($this->model_extension_module_isearch->hasEngine()) {
            $output .= $this->initEngine();
        }
    }

    public function script(&$route, &$data) {
        $this->load->model('extension/module/isearch');

        if ($this->model_extension_module_isearch->hasEngine()) {
            $this->document->addScript('catalog/view/javascript/vendor/isenselabs/isearch/isearch.js');

            if (file_exists(DIR_TEMPLATE . $this->config->get('config_theme') . '/stylesheet/vendor/isenselabs/isearch/isearch.css')) {
                $this->document->addStyle('catalog/view/theme/' . $this->config->get('config_theme') . '/stylesheet/vendor/isenselabs/isearch/isearch.css');
            } else {
                $this->document->addStyle('catalog/view/theme/default/stylesheet/vendor/isenselabs/isearch/isearch.css');
            }

            if ($this->model_extension_module_isearch->getSetting('css')) {
                $this->document->addStyle($this->url->link('extension/module/isearch/css', '', true));
            }
        }
    }

    public function css() {
        $this->load->model('extension/module/isearch');

        $this->response->addHeader('Content-Type:text/css');

        if ($this->model_extension_module_isearch->hasEngine()) {
            $css = html_entity_decode(trim($this->model_extension_module_isearch->getSetting('css')));

            $this->response->setOutput($css);
        }
    }

    public function data() {
        session_write_close();

        $this->load->model('extension/module/isearch');

        $keyword = !empty($this->request->post['keyword']) ? $this->request->post['keyword'] : '';

        $data = array(
            'products' => array(),
            'suggestions' => array(),
            'more' => $this->getMoreURL($keyword)
        );

        if (isset($this->request->post['product_ids']) && is_array($this->request->post['product_ids'])) {
            $this->load->model('catalog/product');

            $results = array();

            foreach (array_slice($this->request->post['product_ids'], 0, 100) as $product_id) {
                $results[] = $this->model_catalog_product->getProduct($product_id);
            }

            $data['products'] = $this->prepareProducts($results, $keyword);
        }

        if (!empty($keyword) && !empty($this->request->post['products'])) {
            $this->model_extension_module_isearch->saveSuggestion($keyword, $this->request->post['products']);
            
            $this->logCustomerSearch($keyword, $this->request->post['products']);

            if ($this->model_extension_module_isearch->getSetting('suggestion')) {
                $data['suggestions'] = $this->prepareSuggestions($keyword);
            }
        }

        $this->response->addHeader('Cache-Control: no-cache, must-revalidate');
        $this->response->addHeader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        $this->response->addHeader('Content-type: application/json');
        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function browser() {
        session_write_close();

        $this->load->model('extension/module/isearch');

        $search = array();
        $data = array();

        $data['stamp_status'] = false;
        
        $has_engine = $this->model_extension_module_isearch->hasEngine();
        $is_instant_search = $this->model_extension_module_isearch->getSetting('instant') == 'browser';

        if ($has_engine && $is_instant_search) {
            $engine_id = $this->model_extension_module_isearch->getEngineId();
            $stamp = !empty($this->request->get['stamp']) ? preg_replace('~[^a-z0-9]~i', '', $this->request->get['stamp']) : false;
            $cache_results = $this->cache->get('product.isearch.browser.' . $this->config->get('config_store_id') . '.' . $engine_id);

            $stamp_status = isset($cache_results['stamp']) && $cache_results['stamp'] == $stamp;

            if ($stamp_status) {
                $data['stamp_status'] = true;
            } else if (isset($cache_results['products'])) {
                $data['products'] = $cache_results['products'];
                $data['stamp'] = $cache_results['stamp'];
                $data['order'] = $cache_results['order'];
            } else {
                $is_length_sort = strpos($this->model_extension_module_isearch->getSetting('sort'), 'length_') === 0;
                $is_match_sort = strpos($this->model_extension_module_isearch->getSetting('sort'), 'match_') === 0;

                $this->load->model('localisation/language');

                $languages = $this->model_localisation_language->getLanguages();

                $results = $this->model_extension_module_isearch->getAllProducts();

                $data['products'] = array();

                foreach ($results as $result) {
                    if ($is_length_sort || $is_match_sort) {
                        $sort_data = array();

                        foreach ($languages as $language) {
                            if ($is_length_sort) {
                                $sort_data[$language['language_id']] = (int)$result['sort_' . $language['language_id']];
                            } else {
                                $sort_data[$language['language_id']] = html_entity_decode($result['sort_' . $language['language_id']]);
                            }
                        }
                    } else {
                        $sort_data = (int)$result['sort_order'];
                    }

                    $data['products'][] = array(
                        'i' => (int)$result['product_id'],
                        'd' => $result['search_data'],
                        's' => $sort_data
                    );
                }

                $data['stamp'] = md5(json_encode($data['products']));

                if (strpos($this->model_extension_module_isearch->getSetting('sort'), 'match_') === 0) {
                    $data['order'] = 'DESC';
                } else {
                    $data['order'] = 'ASC';
                }

                $this->cache->set('product.isearch.browser.' . $this->config->get('config_store_id') . '.' . $engine_id, array(
                    'products' => $data['products'],
                    'stamp' => $data['stamp'],
                    'order' => $data['order']
                ));
            }
        }

        $this->response->addHeader('Cache-Control: no-cache, must-revalidate');
        $this->response->addHeader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        $this->response->addHeader('Content-type: application/json');
        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setCompression(4);
        $this->response->setOutput(json_encode($data));
    }

    public function ajax() {
        session_write_close();

        $this->load->model('extension/module/isearch');

        $search = array();
        $data = array();

        $data['products'] = array();
        $data['suggestions'] = array();
        $data['more'] = '';

        if ($this->model_extension_module_isearch->hasEngine() && !empty($this->request->get['search'])) {
            $filter_data = array(
                'start' => 0,
                'limit' => min(100, abs((int)$this->model_extension_module_isearch->getSetting('product_limit'))),
                'filter_name' => $this->request->get['search']
            );

            //{HOOK_FILTER_DATA}
            $results = $this->model_extension_module_isearch->getProducts($filter_data);
            $total = $this->model_extension_module_isearch->getTotalProducts($filter_data);

            $data['products'] = $this->prepareProducts($results, $this->request->get['search']);

            //{HOOK_CATEGORY_SEARCH}

            $this->logCustomerSearch($this->request->get['search'], $total);

            if ($this->model_extension_module_isearch->getSetting('suggestion')) {
                $data['suggestions'] = $this->prepareSuggestions($this->request->get['search']);
            }

            $data['more'] = $this->getMoreURL($this->request->get['search']);
        }

        $this->response->addHeader('Cache-Control: no-cache, must-revalidate');
        $this->response->addHeader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        $this->response->addHeader('Content-type: application/json');
        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function getProducts(&$route, &$parameters) {
        $this->handleEvent($route, $parameters, 'getProducts');
    }
    
    public function getTotalProducts(&$route, &$parameters) {
        $this->handleEvent($route, $parameters, 'getTotalProducts');
    }

    protected function handleEvent(&$route, &$parameters, $method) {
        $filter_data = &$parameters[0];

        if ($this->inSearchRoute() && $this->hasSearchKeywords($filter_data)) {
            $this->load->model('extension/module/isearch');

            if ($this->model_extension_module_isearch->hasEngine() && (bool)$this->model_extension_module_isearch->getSetting('standard')) {

                if (isset($filter_data['sort']) && $filter_data['sort'] == 'p.sort_order' && (!isset($this->request->get['sort']) || $this->request->get['sort'] == 'p.sort_order')) {
                    unset($filter_data['sort']);
                    unset($filter_data['order']);
                }

                $route = 'extension/module/isearch/' . $method;
            }
        }
    }

    protected function hasSearchKeywords($filter_data) {
        return isset($filter_data['filter_name']) || isset($filter_data['filter_tag']);
    }

    protected function inSearchRoute() {
        return isset($this->request->get['route']) && $this->request->get['route'] == 'product/search';
    }

    protected function logCustomerSearch($keyword, $products) {
        if ($this->config->get('config_customer_search')) {
            $this->load->model('account/search');

            if ($this->customer->isLogged()) {
                $customer_id = $this->customer->getId();
            } else {
                $customer_id = 0;
            }

            if (isset($this->request->server['REMOTE_ADDR'])) {
                $ip = $this->request->server['REMOTE_ADDR'];
            } else {
                $ip = '';
            }

            $search_data = array(
                'keyword'       => $keyword,
                'category_id'   => 0,
                'sub_category'  => '',
                'description'   => '',
                'products'      => (int)$products,
                'customer_id'   => $customer_id,
                'ip'            => $ip
            );

            $this->model_account_search->addSearch($search_data);
        }
    }

    protected function prepareSuggestions($keyword) {
        $this->load->model('extension/module/isearch');

        $result = array();

        foreach ($this->model_extension_module_isearch->getSuggestions($keyword) as $suggestion) {
            $result[] = array(
                'keyword' => $suggestion['keyword'],
                'href' => $this->getMoreURL($suggestion['keyword'])
            );
        }


        return $result;
    }

    protected function prepareProducts($results, $keyword) {
        $this->load->model('extension/module/isearch');
        $this->load->model('tool/image');

        $products = array();

        foreach ($results as $result) {
            if ((bool)$this->model_extension_module_isearch->getSetting('image')) {
                $width = (int)$this->model_extension_module_isearch->getSetting('image_width');
                $height = (int)$this->model_extension_module_isearch->getSetting('image_height');

                if ($result['image'] && is_file(DIR_IMAGE . $result['image']) && is_readable(DIR_IMAGE . $result['image'])) {
                    $target = $result['image'];
                } else {
                    $target = 'no_image.png';
                }

                $image = $this->model_tool_image->resize($target, $width ? $width : 80, $height ? $height : 80);
            } else {
                $image = false;
            }

            if ((bool)$this->model_extension_module_isearch->getSetting('price')) {
                if ((float)$result['price']) {
                    $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $price = false;
                }

                if ((float)$result['special']) {
                    $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $special = false;
                }
            } else {
                $price = false;
                $special = false;
            }

            $products[] = array(
                'name'  => htmlspecialchars(htmlspecialchars_decode($result['name'], ENT_QUOTES), ENT_QUOTES),
                'model' => htmlspecialchars(htmlspecialchars_decode($result['model'], ENT_QUOTES), ENT_QUOTES), 
                'image' => $image,
                'price' => (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) ? $price : '', 
                'special' => (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) ? $special : '',
                'href' => preg_replace('~^https?:~i', '', html_entity_decode($this->url->link('product/product', 'product_id=' . $result['product_id'] . '&search=' . $keyword, true)))
            );
        }

        return $products;
    }

    protected function getSortData($data) {
        $is_length_sort = strpos($this->model_extension_module_isearch->getSetting('sort'), 'length_') === 0;
        $is_match_sort = strpos($this->model_extension_module_isearch->getSetting('sort'), 'match_') === 0;

        if ($is_length_sort || $is_match_sort) {
            $result = array();

            $this->load->model('localisation/language');

            foreach ($this->model_localisation_language->getLanguages() as $language) {
                if ($is_length_sort) {
                    $result[$language['language_id']] = (int)$data['sort_' . $language['language_id']];
                } else {
                    $result[$language['language_id']] = $data['sort_' . $language['language_id']];
                }
            }

            return $result;
        } else {
            return (int)$data['sort_order'];
        }
    }

    protected function getMoreURL($keyword) {
        $this->load->model('extension/module/isearch');

        $description = '';

        if ($this->model_extension_module_isearch->isSearchIn('description')) {
            $description = '&description=true';
        }

        return html_entity_decode($this->url->link('product/search', 'search=' . $keyword . $description, true));
    }

    protected function initEngine() {
        $data = array();

        $data['ajax'] = html_entity_decode($this->url->link('extension/module/isearch/ajax', 'search={KEYWORD}', true));
        $data['browser'] = html_entity_decode($this->url->link('extension/module/isearch/browser', 'stamp={STAMP}', true));
        $data['more'] = $this->getMoreURL('{KEYWORD}');
        $data['fetch_data'] = html_entity_decode($this->url->link('extension/module/isearch/data', '', true));
        $data['height'] = $this->model_extension_module_isearch->getSetting('height');
        $data['height_unit'] = $this->model_extension_module_isearch->getSetting('height_unit');
        $data['height_value'] = $this->model_extension_module_isearch->getSetting('height_value');
        $data['highlight'] = $this->model_extension_module_isearch->getSetting('highlight');
        $data['highlight_value'] = $this->model_extension_module_isearch->getSetting('highlight_value');
        $data['image'] = $this->model_extension_module_isearch->getSetting('image');
        $data['image_width'] = $this->model_extension_module_isearch->getSetting('image_width');
        $data['language_id'] = $this->config->get('config_language_id');
        $data['local_storage_prefix'] = 'extension/module/isearch';
        $data['model'] = (bool)$this->model_extension_module_isearch->getSetting('model');
        $data['price'] = (bool)$this->model_extension_module_isearch->getSetting('price');
        $data['product_limit'] = (int)$this->model_extension_module_isearch->getSetting('product_limit');
        $data['search_in'] = json_encode(array_keys($this->model_extension_module_isearch->getSetting('search_in')));
        $data['selector'] = html_entity_decode($this->model_extension_module_isearch->getSetting('selector'));
        $data['singularization'] = $this->model_extension_module_isearch->getSetting('singularization');
        $data['sort'] = $this->model_extension_module_isearch->getSetting('sort');
        $data['spell'] = json_encode($this->model_extension_module_isearch->getSetting('spell'));
        $data['strictness'] = $this->model_extension_module_isearch->getSetting('strictness');
        $data['type'] = $this->model_extension_module_isearch->getSetting('instant');
        $data['width'] = $this->model_extension_module_isearch->getSetting('width');
        $data['width_unit'] = $this->model_extension_module_isearch->getSetting('width_unit');
        $data['width_value'] = $this->model_extension_module_isearch->getSetting('width_value');

        $text = $this->model_extension_module_isearch->getSetting('text');

        $data['text_loading'] = addslashes($text[$this->config->get('config_language_id')]['loading']);
        $data['text_nothing'] = addslashes($text[$this->config->get('config_language_id')]['nothing']);
        $data['text_heading_suggestion'] = addslashes($text[$this->config->get('config_language_id')]['heading_suggestion']);
        $data['text_heading_results'] = addslashes($text[$this->config->get('config_language_id')]['heading_results']);
        $data['text_more'] = addslashes($text[$this->config->get('config_language_id')]['more']);

        if ((bool)$data['type']) {
            return $this->load->view('extension/module/isearch', $data);
        } else {
            return '';
        }
    }
}