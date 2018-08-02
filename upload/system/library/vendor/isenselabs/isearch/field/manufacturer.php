<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Manufacturer extends Library {
    public function select($is_multilingual = false) {
        return $this->query()
            ->select("LOWER(m.name)")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id");
    }

    public function where($word) {
        return $this->query()->like("m.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        return $this->query()->left_join("manufacturer", "m", "m.manufacturer_id = p.manufacturer_id");
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("CHAR_LENGTH(m.name)")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->select();
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id AND ")
            ->like("m.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id AND ")
            ->like("m.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("m.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("m.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product", "s_p")
            ->left_join("manufacturer", "m", "s_p.manufacturer_id = m.manufacturer_id")
            ->where("s_p.product_id=p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}