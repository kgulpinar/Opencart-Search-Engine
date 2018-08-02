<?php

class ModelExtensionModuleIsearch extends Model {
    private $filter_data;
    private $total = 0;
    private $total_md5 = '';
    private $module_config;
    private $module_id;

    private $allowed_sort = array(
        'pd.name',
        'p.model',
        'p.quantity',
        'p.price',
        'rating',
        'p.number_sales',
        'p.date_added'
    );

    public function __construct($registry) {
        parent::__construct($registry);

        $this->findEngine();
    }

    public function getTotalProducts($filter_data) {
        $this->setFilterData($filter_data);
        
        $this->cachedSearch();

        return $this->total;
    }

    public function saveSuggestion($keyword, $products) {
        $exists_longer = 0 < (int)$this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "isearch_suggestion WHERE keyword LIKE '" . $this->db->escape($keyword) . "%' AND keyword != '" . $this->db->escape($keyword) . "'")->row['count'];
        
        if (!$exists_longer) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "isearch_suggestion WHERE '" . $this->db->escape($keyword) . "' LIKE CONCAT(keyword, '%') AND keyword != '" . $this->db->escape($keyword) . "'");

            $this->db->query("INSERT INTO " . DB_PREFIX . "isearch_suggestion SET keyword='" . $this->db->escape($keyword) . "', products = '" . (int)$products . "' ON DUPLICATE KEY UPDATE products = '" . (int)$products . "'");
        }
    }

    public function getSuggestions($keyword) {
        $sql = "SELECT a.keyword FROM `" . DB_PREFIX . "isearch_suggestion` a WHERE a.keyword LIKE '" . $this->db->escape($keyword) . "%' AND a.keyword != '" . $this->db->escape($keyword) . "' GROUP BY a.products ORDER BY a.products ASC LIMIT 0, " . min(100, (int)abs($this->module_config->get('suggestion_limit')));

        return $this->db->query($sql)->rows;
    }

    public function getAllProducts() {
        $this->db->query("SET SESSION group_concat_max_len = 1000000;");

        //@todo - integrate AdvancedSorting options here
        $sql = "SELECT p.product_id, p.sort_order";

        $sql .= $this->selectSearchIns();

        $sql .= $this->selectLengthSorts();

        $sql .= $this->selectMatchSorts();
        
        $sql .= " FROM `" . DB_PREFIX . "product_to_store` p2s";

        $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = " . $this->config->get('config_store_id');

        if ($exclude_where = $this->getExcludeWheres()) {
            $sql .= " AND " . implode(" AND ", $exclude_where);
        }

        $sql .= " GROUP BY p.product_id ORDER BY p.product_id ASC LIMIT 0,10000";

        return $this->db->query($sql)->rows;
    }

    public function getProducts($filter_data) {
        $this->setFilterData($filter_data);

        $products = $this->cachedSearch();

        if ($products && isset($filter_data['filter_name'])) {
            $this->saveSuggestion($filter_data['filter_name'], $this->total);
        }

        //{HOOK_GET_PRODUCTS}

        return $products;
    }

    protected function getCache($key) {
        return $this->cache->get('product.' . $key . '.' . $this->getCacheKey());
    }

    protected function setCache($key, $data) {
        $this->cache->set('product.' . $key . '.' . $this->getCacheKey(), $data);
    }

    protected function getCacheKey() {
        $params = '';
        $params .= (int)$this->config->get('config_customer_price');
        $params .= (int)$this->getCustomerGroupId();
        $params .= (int)$this->config->get('config_language_id');
        $params .= (int)$this->config->get('config_store_id');
        $params .= serialize($this->filter_data);
        $params .= $this->session->data['currency'];

        return md5($params);
    }

    protected function getCustomerGroupId() {
        if ($this->customer->isLogged()) {
            return $this->customer->getGroupId();;
        } else {
            return $this->config->get('config_customer_group_id');
        }
    }

    protected function cachedSearch() {
        if ($total = $this->getCache('isearch.total')) {
            $this->total = $total;
        }

        if ($result = $this->getCache('isearch')) {
            return $result;
        }

        $result = $this->standardSearch();
        
        $this->setCache('isearch', $result);

        return $result;
    }

    protected function hasAdvancedSorting($type = null) {
        if ($this->module_config->get('sort') == 'advanced_sorting' && $this->config->has('advancedsorting')) {
            $adv_sorting = $this->config->get('advancedsorting');

            if (!empty($adv_sorting) && $adv_sorting['Enabled'] == 'yes' && $adv_sorting['SearchSortingStatus'] == 'yes') {
                if ($adv_sorting['SearchSortingOrder'] == 'RAND') {
                    if (!is_null($type)) {
                        return false;
                    } else {
                        return ' RAND()';
                    }
                } else {
                    if (!is_null($type) && $type == $adv_sorting['SearchSorting']) {
                        return true;
                    } else {
                        if ($adv_sorting['SearchSorting'] == 'p.number_sales') {
                            return ' SUM(op.`quantity`) ' . $adv_sorting['SearchSortingOrder'];
                        } else {
                            return ' ' . $adv_sorting['SearchSorting'] . ' ' . $adv_sorting['SearchSortingOrder'];
                        }
                    }
                }
            }
        }

        return false;
    }

    protected function hasDescriptionFilter() {
        return !empty($this->filter_data['filter_description']);
    }

    protected function hasKeywords() {
        return !empty($this->filter_data['filter_name']);
    }

    protected function getRuleOperator($candidate) {
        switch ($candidate) {
            case 'lt' : return '<';
            case 'gt' : return '>';
            case 'eq' : return '='; 
            case 'ne' : return '!='; 
        }
    }

    protected function getRuleCategoryIds($data) {
        $result = array();

        foreach ($data as $item) {
            $result[] = $item['category_id'];
        }

        return $result;
    }

    protected function getExcludeWheres() {
        $where = array();

        $not_where = array();

        if ($this->module_config->has('exclude')) {
            foreach ($this->module_config->get('exclude') as $rule) {
                switch ($rule['type']) {
                    case 'quantity' : {
                        $not_where[] = 'p.quantity ' . $this->getRuleOperator($rule['data']['operator']) . ' ' . (int)trim($rule['data']['value']);
                    } break;
                    case 'status' : {
                        $not_where[] = 'p.status = ' . (int)trim($rule['data']['value']);
                    } break;
                    case 'category_status' : {
                        $not_where[] = 'p.product_id IN (SELECT p2ct2.product_id FROM `' . DB_PREFIX . 'product_to_category` p2ct2 LEFT JOIN `' . DB_PREFIX . 'category` ct ON (ct.category_id = p2ct2.category_id) WHERE ct.status = ' . (int)trim($rule['data']['value']) . ')';
                    } break;
                    case 'category' : {
                        $category_ids = $this->getRuleCategoryIds($rule['data']);

                        if (!empty($category_ids)) {
                            $not_where[] = 'p.product_id IN (SELECT p2ct.product_id FROM `' . DB_PREFIX . 'product_to_category` p2ct WHERE p2ct.category_id IN (' . implode(',', $category_ids) . '))';
                        }
                    } break;
                    case 'product' : {
                        $product_ids = $this->getRuleProductIds($rule['data']);

                        if (!empty($product_ids)) {
                            $not_where[] = 'p.product_id IN (' . implode(',', $product_ids) . ')';
                        }
                    } break;
                    case 'stock_status' : {
                        $not_where[] = 'p.stock_status_id = ' . (int)trim($rule['data']['stock_status_id']);
                    } break;
                }
            }
        }

        if (!empty($not_where)) {
            $where[] = '!(' . implode(' OR ', $not_where) . ')';
        }

        return $where;
    }

    protected function getKeywords() {
        if ($this->module_config->get('strictness') == 'high') {
            return array($this->filter_data['filter_name']);
        } else {
            return explode(' ', $this->filter_data['filter_name']);
        }
    }

    // Produces a relation containing only product_ids meeting the search criteria, without limit and sort order
    public function filterSubquery($override_filter_data = null) {
        // Not used in the standard iSearch extension, but may be useful when called from a filter
        if (!is_null($override_filter_data)) {
            $this->setFilterData($override_filter_data);
        }

        $join = array();
        $where = $this->getExcludeWheres();

        if ($this->hasKeywords()) {
            // Searching in any field
            $keywords_where = array();

            foreach ($this->getKeywords() as $word) {
                $word_subquery = $this->wordSubquery($word);

                if (!empty($word_subquery)) {
                    $keywords_where[] = 'p.product_id IN (' . $word_subquery . ')';
                }
            }

            if (!empty($keywords_where)) {
                if ($this->module_config->get('strictness') == 'low') {
                    $where[] = '(' . implode(' OR ', $keywords_where) . ')';
                } else {
                    $where[] = '(' . implode(' AND ', $keywords_where) . ')';
                }
            }
        } else if (!empty($this->filter_data['filter_tag'])) {
            // Searching only in tags
            $this->insertJoin($join, 'description');
            $this->insertWhere($where, 'tag', $this->filter_data['filter_tag']);
        }

        $sql = "SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p";

        if (!empty($join)) {
            $sql .= ' ' . implode(' ', $join);
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        //{HOOK_ISEARCH_TEMP_TABLE}

        return $sql;
    }

    public function isSearchIn($key) {
        if ($this->module_config->has('search_in')) {
            $search_in = $this->module_config->get('search_in');

            return isset($search_in[$key]);
        }

        return false;
    }

    protected function selectMatchSorts() {
        if (strpos($this->module_config->get('sort'), 'match_') === FALSE) {
            return '';
        } else {
            $field = substr($this->module_config->get('sort'), 6);

            $result = '';

            $this->load->model('localisation/language');

            foreach ($this->model_localisation_language->getLanguages() as $language) {
                $result .= ', (' . $this->loadField($field)->selectSortByMatch($language['language_id']) . ') as sort_' . $language['language_id'];
            }

            return $result;
        }
    }

    protected function selectLengthSorts() {
        if (strpos($this->module_config->get('sort'), 'length_') === FALSE) {
            return '';
        } else {
            $field = substr($this->module_config->get('sort'), 7);

            $result = '';

            $this->load->model('localisation/language');

            foreach ($this->model_localisation_language->getLanguages() as $language) {
                $result .= ', IFNULL((' . $this->loadField($field)->selectSortByLength($language['language_id']) . '), 0) as sort_' . $language['language_id'];
            }

            return $result;
        }
    }

    protected function selectSearchIns() {
        $result = ', CONCAT_WS(" "';

        foreach (array_keys($this->module_config->get('search_in')) as $field) {
            $result .= ', (' . $this->loadField($field)->select($this->module_config->get('language') == 'all') . ')';
        }

        $result .= ") as search_data";

        return $result;
    }

    protected function wordSubquery($word) {
        $join = array();

        $keyword_where = array();

        //{HOOK_SUBQUERY_JOINS_AND_WHERES}

        if ($this->isSearchIn('name')) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'name', $word);
        }

        if ($this->isSearchIn('model')) {
            $this->insertWhere($keyword_where, 'model', $word);
        }

        if ($this->isSearchIn('upc')) {
            $this->insertWhere($keyword_where, 'upc', $word);
        }

        if ($this->isSearchIn('sku')) {
            $this->insertWhere($keyword_where, 'sku', $word);
        }

        if ($this->isSearchIn('ean')) {
            $this->insertWhere($keyword_where, 'ean', $word);
        }

        if ($this->isSearchIn('jan')) {
            $this->insertWhere($keyword_where, 'jan', $word);
        }

        if ($this->isSearchIn('isbn')) {
            $this->insertWhere($keyword_where, 'isbn', $word);
        }

        if ($this->isSearchIn('mpn')) {
            $this->insertWhere($keyword_where, 'mpn', $word);
        }

        if ($this->isSearchIn('manufacturer')) {
            $this->insertJoin($join, 'manufacturer');
            $this->insertWhere($keyword_where, 'mpn', $word);
        }

        if ($this->isSearchIn('attribute')) {
            $this->insertJoin($join, 'attribute_value');
            $this->insertJoin($join, 'attribute');
            $this->insertWhere($keyword_where, 'attribute', $word);
        }

        if ($this->isSearchIn('attribute_value')) {
            $this->insertJoin($join, 'attribute_value');
            $this->insertWhere($keyword_where, 'attribute_value', $word);
        }

        if ($this->isSearchIn('attribute_group')) {
            $this->insertJoin($join, 'attribute_value');
            $this->insertJoin($join, 'attribute');
            $this->insertJoin($join, 'attribute_group');
            $this->insertWhere($keyword_where, 'attribute_group', $word);
        }

        if ($this->isSearchIn('category')) {
            $this->insertJoin($join, 'category');
            $this->insertWhere($keyword_where, 'category', $word);
        }

        if ($this->isSearchIn('filter')) {
            $this->insertJoin($join, 'filter');
            $this->insertWhere($keyword_where, 'filter', $word);
        }

        if ($this->isSearchIn('filter_group')) {
            $this->insertJoin($join, 'filter');
            $this->insertJoin($join, 'filter_group');
            $this->insertWhere($keyword_where, 'filter_group', $word);
        }

        if ($this->isSearchIn('description') || $this->hasDescriptionFilter()) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'description', $word);
        }

        if ($this->isSearchIn('tag')) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'tag', $word);
        }

        if ($this->isSearchIn('location')) {
            $this->insertWhere($keyword_where, 'location', $word);
        }

        if ($this->isSearchIn('option')) {
            $this->insertJoin($join, 'option');
            $this->insertWhere($keyword_where, 'option', $word);
        }

        if ($this->isSearchIn('option_value')) {
            $this->insertJoin($join, 'option');
            $this->insertJoin($join, 'option_value');
            $this->insertWhere($keyword_where, 'option_value', $word);
        }

        if ($this->isSearchIn('meta_description')) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'meta_description', $word);
        }

        if ($this->isSearchIn('meta_keyword')) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'meta_keyword', $word);
        }

        if ($this->isSearchIn('meta_title')) {
            $this->insertJoin($join, 'description');
            $this->insertWhere($keyword_where, 'meta_title', $word);
        }

        $sql = "SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p";

        if (!empty($join)) {
            $sql .= ' ' . implode(' ', $join);
        }

        if (!empty($keyword_where)) {
            $sql .= ' WHERE ' . implode(' OR ', $keyword_where);
        }

        return $sql;
    }

    protected function standardSearch() {
        $sql = "SELECT SQL_CALC_FOUND_ROWS p.product_id";

        if ((isset($this->filter_data['sort']) && $this->filter_data['sort'] == 'rating') || $this->hasAdvancedSorting('rating')) {
            $sql .= ", (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating";
        }

        if (isset($this->filter_data['sort']) && $this->filter_data['sort'] == 'p.price') {
            $sql .= ", (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";
        }

        if (!empty($this->filter_data['filter_category_id'])) {
            $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";

            if (!empty($this->filter_data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        if ((isset($this->filter_data['sort']) && $this->filter_data['sort'] == 'p.number_sales') || $this->hasAdvancedSorting('p.number_sales')) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "order_product` AS `op` ON `op`.`product_id` = `p`.`product_id`";
        }

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = " . $this->config->get('config_store_id');

        if (!empty($this->filter_data['filter_category_id'])) {
            if (!empty($this->filter_data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$this->filter_data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$this->filter_data['filter_category_id'] . "'";
            }

            if (!empty($this->filter_data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $this->filter_data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        if ($this->hasKeywords() || !empty($this->filter_data['filter_tag'])) {
            $sql .= " AND p.product_id IN (" . $this->filterSubquery() . ")";
        }

        if (!empty($this->filter_data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$this->filter_data['filter_manufacturer_id'] . "'";
        }

        $sql .= " GROUP BY p.product_id";

        $sql .= " ORDER BY";

        if (isset($this->filter_data['sort']) && in_array($this->filter_data['sort'], $this->allowed_sort)) {
            if ($this->filter_data['sort'] == 'p.number_sales') {
                $sql .= " SUM(op.`quantity`)";
            } elseif ($this->filter_data['sort'] == 'pd.name' || $this->filter_data['sort'] == 'p.model') {
                $sql .= " LCASE(" . $this->filter_data['sort'] . ")";
            } elseif ($this->filter_data['sort'] == 'p.price') {
                $sql .= " (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
            } else {
                $sql .= " " . $this->filter_data['sort'];
            }

            if (isset($this->filter_data['order']) && ($this->filter_data['order'] == 'DESC')) {
                $sql .= " DESC";
            } else {
                $sql .= " ASC";
            }
        } else if ($this->hasKeywords()) {
            //{HOOK_ISEARCHCORP_SORTING}

            // Default sorting according to the iSearch settings
            if (strpos($this->module_config->get('sort'), 'match_') === 0) {
                // Match logic goes here
                $match_field = substr($this->module_config->get('sort'), 6);

                $sql .= $this->getSortByMatch($match_field);
            } else if (strpos($this->module_config->get('sort'), 'length_') === 0) {
                // Length logic goes here
                $length_field = substr($this->module_config->get('sort'), 6);

                $sql .= " (" . $this->loadField($field)->selectSortByLength($this->config->get('config_language_id')) . ") ASC";
            } else if ($this->module_config->get('sort') == 'advancedsorting' && $adv_sorting = $this->hasAdvancedSorting()) {
                $sql .= $adv_sorting;
            } else {
                $sql .= " " . $this->module_config->get('sort') . " ASC";
            }
        } else {
            // No keywords, so revert to sort_order
            $sql .= " p.sort_order ASC";
        }

        $sql .= ", p.product_id ASC";

        if (isset($this->filter_data['start']) || isset($this->filter_data['limit'])) {
            if ($this->filter_data['start'] < 0) {
                $this->filter_data['start'] = 0;
            }

            if ($this->filter_data['limit'] < 1) {
                $this->filter_data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$this->filter_data['start'] . "," . (int)$this->filter_data['limit'];
        }

        $product_data = array();

        $query = $this->db->query($sql);

        $this->calculateTotal();

        $this->load->model('catalog/product');

        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->model_catalog_product->getProduct($result['product_id']);
        }

        return $product_data;
    }

    protected function loadField($field) {
        $class = 'vendor\\isenselabs\\isearch\\field\\' . $field;

        return new $class($this->registry);
    }

    protected function insertWhere(&$where, $field, $word) {
        $where[] = $this->getWhere($field, $word);
    }

    protected function insertJoin(&$join, $field) {
        if (!array_key_exists($field, $join)) {
            $join[$field] = $this->getJoin($field);
        }
    }

    protected function getWhere($field, $word) {
        return $this->loadField($field)->where($word);
    }

    protected function getJoin($field) {
        return $this->loadField($field)->join($this->module_config->get('language') == 'all');
    }

    protected function getSortByMatch($field_name) {
        $full_phrase = implode(' ', $this->getKeywords());
        $keywords = explode(' ', $full_phrase);
        $magnitude = count($keywords);
        $field = $this->loadField($field_name);

        return " (CASE " . 
            " WHEN EXISTS (". $field->matchPhraseBeginning($full_phrase) . ") THEN " . ($magnitude * 3 + 1) . 
            " WHEN EXISTS (". $field->matchPhraseAnywhere($full_phrase) . ") THEN " . ($magnitude * 2 + 1) . 
            " WHEN EXISTS (". $field->matchAnyKeywordBeginning($keywords) . ") THEN (" . $magnitude . " + (" . $field->matchSumKeywords($keywords) . "))" . 
            " ELSE (" . $field->matchSumKeywords($keywords) . ") END) DESC";
    }

    protected function calculateTotal() {
        $total = (int)$this->db->query("SELECT FOUND_ROWS() as total")->row['total'];

        $this->setCache('isearch.total', $total);

        $this->total = $total;
    }

    protected function setFilterData($filter_data) {
        $this->setInternalEncoding("UTF-8");

        //{HOOK_SET_FILTER_DATA}

        // Keyword search improvements
        if (isset($filter_data['filter_tag'])) {
            $filter_data['filter_tag'] = $this->improveKeywords($filter_data['filter_tag']);
        }

        if (isset($filter_data['filter_name'])) {
            $filter_data['filter_name'] = $this->improveKeywords($filter_data['filter_name']);
        }

        // Sanitize filter data
        if (isset($filter_data['sort']) && !in_array($filter_data['sort'], $this->allowed_sort)) {
            unset($filter_data['sort']);
        }

        if (isset($filter_data['order']) && !in_array($filter_data['order'], array('ASC', 'DESC'))) {
            $filter_data['order'] = 'ASC';
        }

        if (isset($filter_data['filter_category_id']) && !is_numeric($filter_data['filter_category_id'])) {
            $filter_data['filter_category_id'] = 0;
        }

        if (isset($filter_data['filter_sub_category'])) {
            $filter_data['filter_sub_category'] = !empty($filter_data['filter_sub_category']);
        }

        // Set filter data
        $this->filter_data = $filter_data;
    }

    protected function improveKeywords($keywords) {
        $keywords = $this->convertEncoding($keywords, "UTF-8");

        // Custom spell check
        if ($this->module_config->has('spell')) {
            foreach ($this->module_config->get('spell') as $values) {
                if (!empty($values['search'])) {
                    if ($values['search'][0] == '/') {
                        $keywords = preg_replace($values['search'], $values['replace'], $keywords);
                    } else {
                        $keywords = str_replace($values['search'], $values['replace'], $keywords);   
                    }
                }
            }
        }

        // Singularize (works only when strict search is disabled)
        if ((bool)$this->module_config->get('singularization') && $this->module_config->get('strictness') != 'high') {
            $words = explode(' ', $keywords);

            foreach ($words as &$word) {
                $word = preg_replace('/(s|es)$/', '', $word);  
            }

            $keywords = implode(' ', $words);
        }

        // Remove any extra white spaces
        if ($this->module_config->get('strictness') != 'high') {
            $keywords = trim(preg_replace('~\s+~', ' ', $keywords));
        }

        // Set to lowercase
        $keywords = $this->strtolower($keywords);

        return $keywords;
    }

    protected function setInternalEncoding($encoding) {
        // Set internal multibyte encoding to UTF-8
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding($encoding);
        }
    }

    protected function convertEncoding($keywords, $encoding) {
        if (function_exists('mb_convert_encoding')) {
            $keywords = mb_convert_encoding($keywords, $encoding);
        }

        return $keywords;
    }

    public function getActiveEngines() {
        $result = array();

        foreach ($this->getModulesByCode('isearch') as $module) {
            $module_info = json_decode($module['setting'], true);

            if (empty($module_info['status'])) {
                continue;
            }

            $config = new Config();

            foreach ($module_info as $key => $value) {
                $config->set($key, $value);
            }

            $result[$module['module_id']] = $config;
        }

        return $result;
    }

    public function hasEngine() {
        return !empty($this->module_config);
    }

    public function findEngine() {
        foreach ($this->getActiveEngines() as $module_id => $config) {
            foreach ($config->get('store') as $store_id => $store_status) {
                if ($store_status && $store_id == $this->config->get('config_store_id')) {
                    $this->module_config = $config;
                    $this->module_id = $module_id;
                    return;
                }
            }
        }
    }

    public function getEngineId() {
        return $this->module_id;
    }

    public function getSetting($key) {
        if ($this->hasEngine()) {
            return $this->module_config->get($key);
        }
    }

    public function getModulesByCode($code) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "module` WHERE `code` = '" . $this->db->escape($code) . "' ORDER BY `name`");

        return $query->rows;
    }

    // Multibyte functions
    
    public function strtolower($string) {
        return (function_exists('mb_strtolower')) ? mb_strtolower($string) : strtolower($string);
    }
    
    public function strlen($string) {
        return (function_exists('mb_strlen')) ? mb_strlen($string) : strlen($string);
    }
    
    public function substr($string, $start) {
        $arg = func_get_args();
        if (isset($arg[2])) return (function_exists('mb_substr')) ? mb_substr($string, $start, $arg[2]) : substr($string, $start, $arg[2]);
        else return (function_exists('mb_substr')) ? mb_substr($string, $start) : substr($string, $start);
    }
    
    public function strstr($string, $needle) {
        $arg = func_get_args();
        if (isset($arg[2])) return (function_exists('mb_strstr')) ? mb_strstr($string, $needle, $arg[2]) : strstr($string, $needle, $arg[2]);
        else return (function_exists('mb_strstr')) ? mb_strstr($string, $needle) : strstr($string, $needle);
    }
}