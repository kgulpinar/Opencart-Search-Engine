<?php

namespace vendor\isenselabs\isearch;

use vendor\isenselabs\isearch\Query;

class Library {
    protected $registry;

    public function __construct($registry) {
        $this->registry = $registry;
    }

    public function __get($key) {
        return $this->registry->get($key);
    }

    public function __set($key, $value) {
        $this->registry->set($key, $value);
    }

    protected function query() {
        return new Query($this->registry);
    }
}