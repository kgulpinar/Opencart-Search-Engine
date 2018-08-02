<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Tag extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_pd.tag) SEPARATOR '|')")
                ->from("product_description", "s_pd")
                ->where("s_pd.product_id = p.product_id")
                ->group_by("p.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("pd.tag", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        return "";
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_pd.tag))")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$language_id . "'")
            ->group_by("p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_pd.tag) SEPARATOR '|')")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$language_id . "'")
            ->group_by("p.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_pd.product_id")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$this->config->get('config_language_id') . "' AND ")
            ->like("s_pd.tag", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_pd.product_id")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$this->config->get('config_language_id') . "' AND ")
            ->like("s_pd.tag", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_pd.tag", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_pd.product_id")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$this->config->get('config_language_id') . "' AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_pd.tag", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_description", "s_pd")
            ->where("s_pd.product_id = p.product_id AND s_pd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}