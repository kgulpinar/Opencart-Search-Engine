<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Upc extends Library {
    public function select($is_multilingual = false) {
        return $this->query()->select("LOWER(p.upc)");
    }

    public function where($word) {
        return $this->query()->like("p.upc", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        return "";
    }

    public function selectSortByLength($language_id) {
        return $this->query()->select("CHAR_LENGTH(p.upc)");
    }

    public function selectSortByMatch($language_id) {
        return $this->select();
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->where("s_p.product_id = p.product_id AND ")
            ->like("s_p.upc", $this->db->escape($full_phrase) . "%");
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->where("s_p.product_id = p.product_id AND ")
            ->like("s_p.upc", "%" . $this->db->escape($full_phrase) . "%");
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_p.upc", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_p.product_id")
            ->from("product", "s_p")
            ->where("s_p.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_p.upc", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product", "s_p")
            ->where("s_p.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}