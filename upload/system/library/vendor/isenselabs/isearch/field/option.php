<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Option extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_od.name) SEPARATOR '|')")
                ->from("product_option", "s_po")
                ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id")
                ->where("s_po.product_id = p.product_id")
                ->group_by("s_po.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("od.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->left_join("product_option_value", "pov", "pov.product_id = p.product_id")
                ->left_join("option_description", "od", "od.option_id = pov.option_id");
        } else {
            return $this->query()
                ->left_join("product_option_value", "pov", "pov.product_id = p.product_id")
                ->left_join("option_description", "od", "od.option_id = pov.option_id AND od.language_id='" . (int)$this->config->get('config_language_id') . "'");
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_od.name))")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$language_id . "'")
            ->where("s_po.product_id = p.product_id")
            ->group_by("s_po.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_od.name) SEPARATOR '|')")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$language_id . "'")
            ->where("s_po.product_id = p.product_id")
            ->group_by("s_po.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND ")
            ->like("s_od.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND ")
            ->like("s_od.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_od.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_po.product_id")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_od.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_option", "s_po")
            ->left_join("option_description", "s_od", "s_od.option_id = s_po.option_id AND s_od.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_po.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}