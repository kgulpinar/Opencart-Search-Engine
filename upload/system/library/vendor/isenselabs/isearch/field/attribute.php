<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Attribute extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_ad.name) SEPARATOR '|')")
                ->from("product_attribute", "s_pa")
                ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id")
                ->where("s_pa.product_id = p.product_id")
                ->group_by("p.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("ad.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->left_join("attribute_description", "ad", "pa.attribute_id = ad.attribute_id")
                ->left_join("attribute", "a", "a.attribute_id = ad.attribute_id");
        } else {
            return $this->query()
                ->left_join("attribute_description", "ad", "pa.attribute_id = ad.attribute_id AND ad.language_id='" . (int)$this->config->get('config_language_id') . "'")
                ->left_join("attribute", "a", "a.attribute_id = ad.attribute_id");
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_ad.name))")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$language_id . "'")
            ->where("s_pa.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_ad.name) SEPARATOR '|')")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$language_id . "'")
            ->where("s_pa.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_pa.product_id")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pa.product_id = p.product_id AND ")
            ->like("s_ad.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_pa.product_id")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pa.product_id = p.product_id AND ")
            ->like("s_ad.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_ad.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_pa.product_id")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pa.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_ad.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_attribute", "s_pa")
            ->left_join("attribute_description", "s_ad", "s_ad.attribute_id = s_pa.attribute_id AND s_ad.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pa.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}