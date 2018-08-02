<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Option_Value extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(IFNULL(s_ovd.name, s_po.value)) SEPARATOR '|')")
                ->from("product_option", "s_po")
                ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
                ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id")
                ->where("s_po.product_id = p.product_id")
                ->group_by("s_po.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return "(LOWER(ovd.name) LIKE '%" . $this->db->escape($word) . "%' COLLATE utf8_unicode_ci OR LOWER(po.value) LIKE '%" . $this->db->escape($word) . "%' COLLATE utf8_unicode_ci)";
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return "LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id) LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id)";
        } else {
            return "LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id='" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id)";
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(IFNULL(s_ovd.name, s_po.value)))")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$language_id . "'")
            ->where("s_po.product_id = p.product_id AND !((s_ovd.name = '' OR s_ovd.name IS NULL) AND s_po.value = '')")
            ->group_by("s_po.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(IFNULL(s_ovd.name, s_po.value)) SEPARATOR '|')")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$language_id . "'")
            ->where("s_po.product_id = p.product_id")
            ->group_by("s_po.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND ")
            ->like("IFNULL(s_ovd.name, s_po.value)", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND ")
            ->like("IFNULL(s_ovd.name, s_po.value)", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("IFNULL(s_ovd.name, s_po.value)", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("IFNULL(s_ovd.name, s_po.value)", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_option", "s_po")
            ->left_join("product_option_value", "s_pov", "s_po.product_option_id = s_pov.product_option_id")
            ->left_join("option_value_description", "s_ovd", "s_ovd.option_value_id = s_pov.option_value_id AND s_ovd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}