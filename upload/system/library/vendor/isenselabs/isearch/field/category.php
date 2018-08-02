<?php

namespace vendor\isenselabs\isearch\field;

use vendor\isenselabs\isearch\Library;

class Category extends Library {
    public function select($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->select("GROUP_CONCAT(DISTINCT LOWER(s_cd.name) SEPARATOR '|')")
                ->from("product_to_category", "s_p2c")
                ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id")
                ->where("s_p2c.product_id = p.product_id")
                ->group_by("p.product_id");
        } else {
            return $this->selectSortByMatch($this->config->get('config_language_id'));
        }
    }

    public function where($word) {
        return $this->query()->like("cd.name", "%" . $this->db->escape($word) . "%");
    }

    public function join($is_multilingual = false) {
        if ($is_multilingual) {
            return $this->query()
                ->left_join("product_to_category", "p2c", "p2c.product_id = p.product_id")
                ->left_join("category_description", "cd", "cd.category_id = p2c.category_id");
        } else {
            return $this->query()
                ->left_join("product_to_category", "p2c", "p2c.product_id = p.product_id")
                ->left_join("category_description", "cd", "cd.category_id = p2c.category_id AND cd.language_id='" . (int)$this->config->get('config_language_id') . "'");
        }
    }

    public function selectSortByLength($language_id) {
        return $this->query()
            ->select("MIN(CHAR_LENGTH(s_cd.name))")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$language_id . "'")
            ->where("s_p2c.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function selectSortByMatch($language_id) {
        return $this->query()
            ->select("GROUP_CONCAT(DISTINCT LOWER(s_cd.name) SEPARATOR '|')")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$language_id . "'")
            ->where("s_p2c.product_id = p.product_id")
            ->group_by("p.product_id");
    }

    public function matchPhraseBeginning($full_phrase) {
        return $this->query()
            ->select("s_p2c.product_id")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_p2c.product_id = p.product_id AND ")
            ->like("s_cd.name", $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchPhraseAnywhere($full_phrase) {
        return $this->query()
            ->select("s_p2c.product_id")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_p2c.product_id = p.product_id AND ")
            ->like("s_cd.name", "%" . $this->db->escape($full_phrase) . "%")
            ->limit(0, 1);
    }

    public function matchAnyKeywordBeginning($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = $this->query()->like("s_cd.name", $this->db->escape($keyword) . "%");
        }

        return $this->query()
            ->select("s_p2c.product_id")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_p2c.product_id = p.product_id AND (" . implode(" OR ", $keyword_matches) . ")")
            ->limit(0, 1);
    }

    public function matchSumKeywords($keywords) {
        $keyword_matches = array();

        foreach ($keywords as $keyword) {
            $keyword_matches[] = "(" . $this->query()->like("s_cd.name", "%" . $this->db->escape($keyword) . "%") . ")";
        }

        return $this->query()
            ->select("(" . implode(" + ", $keyword_matches) . ") as match_count")
            ->from("product_to_category", "s_p2c")
            ->left_join("category_description", "s_cd", "s_cd.category_id = s_p2c.category_id AND s_cd.language_id='" . (int)$this->config->get('config_language_id') . "'")
            ->where("s_p2c.product_id = p.product_id")
            ->order_by("match_count", "DESC")
            ->limit(0, 1);
    }
}