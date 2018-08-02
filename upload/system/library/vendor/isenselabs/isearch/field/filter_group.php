<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Filter_Group extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_fgd.name) SEPARATOR '|')")
                ->from("product_filter", "s_pf")
                ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
                ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id")
                ->where("s_pf.product_id = p.product_id")
                ->group_by("p.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("fgd.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return "LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id)";
        } else {
            return "LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id AND fgd.language_id='" . (int)$this->config->get('config_language_id') . "')";
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_fgd.name))")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$language_id . "'")
            ->where("s_pf.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_fgd.name) SEPARATOR '|')")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$language_id . "'")
            ->where("s_pf.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND ")
            ->like("s_fgd.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND ")
            ->like("s_fgd.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_fgd.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_fgd.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_filter", "s_pf")
            ->left_join("filter", "s_f", "s_f.filter_id = s_pf.filter_id")
            ->left_join("filter_group_description", "s_fgd", "s_fgd.filter_group_id = s_f.filter_group_id AND s_fgd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}