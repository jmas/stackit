<?php

namespace Stackit\Models;

/**
 *
 */
class Record {
  /**
   *
   */
  protected $data;

  /**
   *
   */
  public function __construct($data) {
    $this->data = $data;
  }

  /**
   *
   */
  public function __get($name) {
    return $this->get($name);
  }

  /**
   *
   */
  public function __set($name, $value) {
    $this->set($name, $value);
  }

  /**
   *
   */
  public function getId() {
    return $this->get('id');
  }

  /**
   *
   */
  public function get($name, $defaultValue=null) {
    return isset($this->data[$name]) ? $this->data[$name]: $defaultValue;
  }

  /**
   *
   */
  public function set($name, $value) {
    $this->data[$name] = $value;
    return $this;
  }

  /**
   *
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   *
   */
  public function asArray() {
    return $this->data;
  }
}
