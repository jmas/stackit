<?php

namespace Stackit\Models;

/**
 *
 */
class Storage {
  /**
   *
   */
  protected $id;
  
  /**
   *
   */
  protected $alias;
  
  /**
   *
   */
  protected $name;
  
  /**
   *
   */
  protected $fields;

  /**
   *
   */
  protected $tablePrefix;

  /**
   *
   */
  public function __construct($id, $alias, $name, $fields=[], $tablePrefix='') {
    $this->id = $id;
    $this->alias = $alias;
    $this->name = $name;
    $this->fields = $fields;
    $this->tablePrefix = $tablePrefix;
  }

  /**
   *
   */
  public function getId() {
    return $this->id;
  }

  /**
   *
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   *
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   *
   */
  public function getName() {
    return $this->name;
  }

  /**
   *
   */
  public function getAlias() {
    return $this->alias;
  }

  /**
   *
   */
  public function setFields($fields) {
    $this->fields = $fields;
    return $this;
  }

  /**
   *
   */
  public function getRecordTableName() {
    return $this->tablePrefix . $this->getAlias();
  }

  /**
   *
   */
  public function asArray() {
     return [
      'id'=>$this->getId(),
      'alias'=>$this->getAlias(),
      'name'=>$this->getName(),
     ];
  }

  /**
   *
   */
  public function getFieldsAsArray() {
    return array_map(function ($item) {
      return $item->asArray();
    }, $this->getFields());
  }
}
