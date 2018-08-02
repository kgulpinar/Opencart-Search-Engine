<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Filter extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_fd.name) SEPARATOR '|')")
                ->from("product_filter", "s_pf")
                ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id")
                ->where("s_pf.product_id = p.product_id")
                ->group_by("p.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("fd.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->left_join("product_filter", "pf", "pf.product_id = p.product_id")
                ->left_join("filter_description", "fd", "fd.filter_id = pf.filter_id")
                ->left_join("filter", "f", "f.filter_id = fd.filter_id");
        } else {
            return $this->query()
                ->left_join("product_filter", "pf", "pf.product_id = p.product_id")
                ->left_join("filter_description", "fd", "fd.filter_id = pf.filter_id AND fd.language_id='" . (int)$this->config->get('config_language_id') . "'")
                ->left_join("filter", "f", "f.filter_id = fd.filter_id");
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_fd.name))")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$language_id . "'")
            ->where("s_pf.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_fd.name) SEPARATOR '|')")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$language_id . "'")
            ->where("s_pf.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND ")
            ->like("s_fd.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND ")
            ->like("s_fd.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_fd.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_pf.product_id")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_fd.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_filter", "s_pf")
            ->left_join("filter_description", "s_fd", "s_fd.filter_id = s_pf.filter_id AND s_fd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_pf.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}