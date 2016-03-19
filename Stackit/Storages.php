<?php

namespace Stackit;

use Stackit\Models\Field as Field;
use Stackit\Models\Storage as Storage;

/**
 *
 */
class Storages {
  /**
   *
   */
  protected $connection;

  /**
   *
   */
  protected $settings = [
    'fieldTypesPath'=>'/field-types',
    'itemsTable'=>'storage',
    'fieldsTable'=>'storage_field',
    'recordsTablePrefix'=>'storage__',
  ];

  /**
   *
   */
  public function __construct($connection, $settings=[]) {
    $this->connection = $connection;
    $this->setSettings($settings);
  }

  /**
   *
   */
  public function __get($name) {
    return $this->getRecords($name);
  }

  /**
   *
   */
  public function find($alias) {
    $this->runHook('before-type-find', [$alias]);
    $typeTableName = $this->getSetting('itemsTable');
    $sqlQuery = "SELECT * FROM {$typeTableName}
                 WHERE alias=?";
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute([$alias]) === false) {
      return false;
    }
    $item = $sth->fetch();
    if (!$item) {
      return null;
    } 
    $storage = new Storage($item['id'], $item['alias'], $item['name'],
                  $this->getFieldsById($item['id']), $this->getSetting('recordsTablePrefix'));
    $this->runHook('after-type-find', [$storage]);
    return $storage;
  }

  /**
   *
   */
  public function findAll() {
    $this->runHook('before-type-find');
    $typeTableName = $this->getSetting('itemsTable');
    $sqlQuery = "SELECT * FROM {$typeTableName}";
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute() === false) {
      return false;
    }
    $storages = array_map(function ($item) {
      return new Storage($item['id'], $item['alias'], $item['name'],
                 $this->getFieldsById($item['id']), $this->getSetting('recordsTablePrefix'));
    }, $sth->fetchAll());
    $this->runHook('after-type-find', [$storages]);
    return $storages;
  }

  /**
   *
   */
  public function findById($id) {
    $typeTableName = $this->getSetting('itemsTable');
    $sqlQuery = "SELECT * FROM {$typeTableName}
                 WHERE id=?";
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute([$id]) === false) {
      return false;
    }
    $item = $sth->fetch();
    if (!$id) {
      return null;
    }
    return new Storage($item['id'], $item['alias'], $item['name'],
               $this->getFieldsById($item['id']), $this->getSetting('recordsTablePrefix'));
  }

  /**
   *
   */
  public function save($storage) {
    $this->runHook('before-type-save', [$storage]);
    $typeTableName = $this->getSetting('itemsTable');
    $fieldTableName = $this->getSetting('fieldsTable');
    $values = [];
    // create data type
    if ($storage->getId()) {
      $sqlQuery = "UPDATE {$typeTableName} SET name=? LIMIT 1";
      $values[] = $storage->getName();
    } else {
      $sqlQuery = "INSERT INTO {$typeTableName}(alias, name) VALUES(?, ?)";
      $values[] = $storage->getAlias();
      $values[] = $storage->getName();
    }
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute($values) === false) {
      return false;
    }
    if (! $storage->getId()) {
      $storage->setId($this->connection->lastInsertId());
    }
    // fields
    $fields = $storage->getFields();
    $existsFields = $storage->getId() ? $this->getFieldsById($storage->getId()): [];
    $removeFields = [];
    $createFields = [];
    $updateFields = [];
    $sqlQueries = [];
    // prepare fields
    foreach ($fields as $field) {
      $found = false;
      foreach ($existsFields as $existsField) {
        if ($field->getAlias() === $existsField->getAlias()) {
          $found = true;
          break;
        }
      }
      if (! $found) {
        $createFields[] = $field;
      }
    }
    // update and remove
    foreach ($existsFields as $existsField) {
      $found = false;
      foreach ($fields as $field) {
        if ($field->getAlias() === $existsField->getAlias()) {
          $found = true;
          break;
        }
      }
      if (! $found) {
        $removeFields[] = $existsField;
      } else {
        $updateFields[] = $existsField;
      }
    }
    // build queries
    $recordTableName = $storage->getRecordTableName();
    $sqlQueries[] = "CREATE TABLE IF NOT EXISTS {$recordTableName}
                     (id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                     created_at INT(6), updated_at INT(6))";
    foreach ($removeFields as $field) {
      $sqlQueries[] = "DELETE FROM {$fieldTableName}
                       WHERE alias='{$field->getAlias()}'
                       AND data_type_id='{$storage->getId()}'";
      $sqlQueries[] = "ALTER TABLE {$recordTableName}
                       DROP COLUMN {$field->getAlias()}";
    }
    foreach ($createFields as $field) {
      $encodedOptions = json_encode($field->getOptions());
      $sqlQueries[] = "INSERT INTO {$fieldTableName}(alias, name, type, options, data_type_id)
                       VALUES('{$field->getAlias()}', '{$field->getName()}',
                       '{$field->getType()}', '{$encodedOptions}', '{$storage->getId()}')";
      $sqlQueries[] = "ALTER TABLE {$recordTableName}
                       ADD {$field->getAlias()} {$field->getSqlType()} NOT NULL";
    }
    foreach ($updateFields as $field) {
      $encodedOptions = json_encode($field->getOptions());
      $sqlQueries[] = "UPDATE {$fieldTableName} SET name='{$field->getName()}', options='{$encodedOptions}' 
                       WHERE alias='{$field->getAlias()}'
                       AND data_type_id='{$storage->getAlias()}'";
    }
    $this->connection->beginTransaction();
    try {
      foreach ($sqlQueries as $sqlQuery) {
        if ($this->connection->exec($sqlQuery) === false) {
          return false;
        }
      }
      $this->connection->commit();
      $this->fields = $fields;
    } catch (\Exception $e) {
      $this->connection->rollback();
      return false;
    }
    $storage = $this->findById($storage->getId());
    $this->runHook('after-type-save', [$storage]);
    return $storage;
  }

  /**
   *
   */
  public function saveAll($storages) {
    foreach ($storages as $storage) {
      if (! $this->save($storage)) {
        return false;
      }
    }
    return true;
  }

  /**
   *
   */
  public function getRecords($alias) {
    $item = $this->find($alias);
    if (!$item) {
      return false;
    }
    return new Records($this->connection, $item);
  }

  /**
   *
   */
  public function asArray() {
    return array_map(function ($item) {
      return $item->asArray();
    }, $this->findAll());
  }

  /**
   *
   */
  protected function getFieldsById($id) {
    $fieldTableName = $this->getSetting('fieldsTable');
    $sqlQuery = "SELECT * FROM {$fieldTableName}
                 WHERE data_type_id='{$id}'";
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute() === false) {
      return false;
    }
    $fields = $sth->fetchAll();
    return array_map(function($item) {
      return new Field($item['id'], $item['alias'], $item['name'], $item['type'],
                 json_decode($item['options'], true), $this->getSetting('fieldTypesPath'));
    }, $fields);
  }

  /**
   *
   */
  protected function runHook($name, $args=[]) {
    $fnPath = HOOKS_PATH . '/' . $name . '.php';
    if (file_exists($fnPath)) {
      call_user_func_array(require($fnPath), $args);
    }
  }

  /**
   *
   */
  protected function getSetting($name) {
    if (isset($this->settings[$name])) {
      return $this->settings[$name];
    }
    return null;
  }

  /**
   *
   */
  protected function setSettings($settings) {
    foreach ($settings as $key => $value) {
      $this->settings[$key] = $value;
    }
  }
}
