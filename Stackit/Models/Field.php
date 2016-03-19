<?php

namespace Stackit\Models;

/**
 *
 */
class Field {
  /**
   *
   */
  protected $id;
  
  /**
   *
   */
  protected $name;
  
  /**
   *
   */
  protected $alias;
 
  /**
   *
   */
  protected $type;
  
  /**
   *
   */
  protected $options;
  
  /**
   *
   */
  protected $manifest;

  /**
   *
   */
  protected $typesPath;

  /**
   *
   */
  public function __construct($id, $alias, $name, $type, $options=null, $typesPath='/fields-types') {
    $this->id = $id;
    $this->name = $name;
    $this->alias = $alias;
    $this->type = $type;
    $this->options = $options;
    $this->typesPath = $typesPath;
    $this->manifest = $this->getManifest();
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
  public function getType() {
    return $this->type;
  }

  /**
   *
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   *
   */
  public function getSqlType() {
    return $this->manifest['sqlType'];
  }

  /**
   *
   */
  public function asArray() {
    return [
      'id'=>$this->getId(),
      'name'=>$this->getName(),
      'alias'=>$this->getAlias(),
      'type'=>$this->getType(),
      'optinos'=>$this->getOptions(),
    ];
  }

  /**
   *
   */
  public function getValidateFn() {
    $fnPath = $this->typesPath . '/' . $this->getType() . '/validate.php';
    if (file_exists($fnPath)) {
      return require($fnPath);
    }
    return null;
  }

  /**
   *
   */
  public function getFormatFn() {
    $formatFnPath = $this->typesPath . '/' . $this->getType() . '/format.php';
    if (file_exists($formatFnPath)) {
      return require($formatFnPath);
    }
    return null;
  }

  /**
   *
   */
  protected function getManifest() {
    $manifestPath = $this->typesPath . '/' . $this->getType() . '/manifest.php';
    if (file_exists($manifestPath)) {
      return require($manifestPath);
    }
    return null;
  }
}
