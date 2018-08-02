<?php

namespace vendor\isenselabs\isearch;

class Field extends Library {
    protected $fields = array();

    public function __construct() {
        $this->initFields();
    }

    public function getFields() {
        return $this->fields;
    }

    protected function initFields() {
        $files = scandir(DIR_SYSTEM . 'library/vendor/isenselabs/isearch/field');

        foreach ($files as $file) {
            if (in_array($file, array('.', '..'))) {
                continue;
            }

            $key = basename($file, '.php');
            $class = 'vendor\\isenselabs\\isearch\\field\\' . $key;

            $this->fields[$key] = new $class($this->registry);
        }
    }
}