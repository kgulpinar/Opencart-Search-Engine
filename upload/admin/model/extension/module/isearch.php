<?php

class ModelExtensionModuleIsearch extends Model {
    private $indexes = array(
        array(
            'table' => 'product_option_value',
            'column' => 'product_option_id'
        ),
        array(
            'table' => 'product_option_value',
            'column' => 'product_id'
        ),
        array(
            'table' => 'product_option_value',
            'column' => 'option_id'
        ),
        array(
            'table' => 'product_option',
            'column' => 'option_id'
        ),
        array(
            'table' => 'product_option',
            'column' => 'product_id'
        )
    );

    public function getEngineStores($stores) {
        $result = array();

        $this->load->model('setting/store');

        if (is_array($stores)) {
            foreach (array_keys($stores) as $store_id) {
                if ($store_id == 0) {
                    $name = $this->config->get('config_name');
                } else {
                    $store_info = $this->model_setting_store->getStore($store_id);
                    $name = $store_info['name'];
                }
                
                $result[$store_id] = array(
                    'name' => $name,
                    'is_default' => $store_id == 0
                );
            }
        }

        return $result;
    }

    public function getStores() {
        $result = array();

        $result[] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name'),
            'is_default' => true
        );

        $this->load->model('setting/store');

        foreach ($this->model_setting_store->getStores() as $store) {
            $result[] = array(
                'store_id' => $store['store_id'],
                'name' => $store['name'],
                'is_default' => false
            );
        }

        return $result;
    }

    public function clearSuggestions($module_id) {
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "isearch_suggestion`");
    }

    public function createTables() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "isearch_suggestion` (`isearch_suggestion_id` int(11) NOT NULL AUTO_INCREMENT, `keyword` varchar(255) NOT NULL, `products` int(11) NOT NULL, PRIMARY KEY (`isearch_suggestion_id`), UNIQUE KEY `keyword` (`keyword`), KEY `products` (`products`)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }

    public function createIndexes() {
        foreach ($this->indexes as $index) {
            $name = 'isearch_' . $index['column'];
            $table = DB_PREFIX . $index['table'];

            if (!$this->indexExists($table, $name)) {
                $this->db->query("ALTER TABLE `" . $table . "` ADD INDEX " . $name . " (" . $index['column'] . ")");
            }
        }
    }

    public function createEvents() {
        $events = array(
            'catalog/view/common/search/after' => 'extension/module/isearch/init',
            'catalog/controller/common/header/before' => 'extension/module/isearch/script',
            'catalog/model/catalog/product/getProducts/before' => 'extension/module/isearch/getProducts',
            'catalog/model/catalog/product/getTotalProducts/before' => 'extension/module/isearch/getTotalProducts'
        );

        $this->load->model('setting/event');

        foreach ($events as $trigger => $action) {
            $this->model_setting_event->addEvent('isearch', $trigger, $action);
        }
    }

    public function dropTables() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "isearch_suggestion`");
    }

    public function deleteEvents() {
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('isearch');
    }

    public function indexExists($table, $name) {
        foreach ($this->db->query("SHOW INDEX FROM " . $table)->rows as $index) {
            if ($index['Key_name'] == $name) {
                return true;
            }
        }

        return false;
    }

    public function dropIndexes() {
        foreach ($this->indexes as $index) {
            $name = 'isearch_' . $index['column'];
            $table = DB_PREFIX . $index['table'];

            if ($this->indexExists($table, $name)) {
                $this->db->query("ALTER TABLE `" . $table . "` DROP INDEX " . $name);
            }
        }
    }
}