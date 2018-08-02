<?php

namespace vendor\isenselabs\isearch;

class Query extends Library {
    private $text = '';

    private function append($string) {
        $this->text .= $string;
    }

    public function __toString() {
        return $this->text;
    }

    public function select($text) {
        $this->append("SELECT " . $text);

        return $this;
    }

    public function from($table, $alias = '') {
        $this->append(" FROM `" . DB_PREFIX . $table . "` " . $alias);

        return $this;
    }

    public function left_join($table, $alias = '', $on) {
        $this->append(" LEFT JOIN `" . DB_PREFIX . $table . "` " . $alias . " ON (" . $on . ")");

        return $this;
    }

    public function where($text) {
        $this->append(" WHERE " . $text);

        return $this;
    }

    public function having($text) {
        $this->append(" HAVING " . $text);

        return $this;
    }

    public function group_by($text) {
        $this->append(" GROUP BY " . $text);

        return $this;
    }

    public function order_by($sort, $order) {
        $this->append(" ORDER BY " . $sort . " " . $order);

        return $this;
    }

    public function like($field, $compare) {
        $this->append(" LOWER(" . $field . ") LIKE '" . $compare . "' COLLATE utf8_unicode_ci");

        return $this;
    }

    public function limit($start, $length) {
        $this->append(" LIMIT " . $start . ", " . $length);

        return $this;
    }
}