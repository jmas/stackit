<?php

namespace Stackit;

use Stackit\Models\Record as Record;

/**
 *
 */
class Records {
  /**
   *
   */
  protected $connection;
  
  /**
   *
   */
  protected $storage;

  /**
   *
   */
  public function __construct($connection, $storage) {
    $this->connection = $connection;
    $this->storage = $storage;
  }

  /**
   *
   */
  public function find($args=null) {
    $this->runHook('before-find', [$args]);
    $params = isset($args['params']) ? $args['params'] : [];
    $args['limit'] = 1;
    $sqlQuery = $this->makeQuery($args);
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute($params) === false) {
      return false;
    }
    $item = $sth->fetch();
    if (! $item) {
      return null;
    }
    $result = new Record($item);
    $this->runHook('after-find', [$result]);
    return $result;
  }

  /**
   *
   */
  public function findAll($args=null) {
    $this->runHook('before-find', [$args]);
    $params = isset($args['params']) ? $args['params'] : [];
    $sqlQuery = $this->makeQuery($args);
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute($params) === false) {
      return false;
    }
    $result = array_map(function ($item) {
      return new Record($item);
    }, $sth->fetchAll());
    $this->runHook('after-find', [$result]);
    return $result;
  }

  /**
   *
   */
  public function findById($id) {
    return $this->find([
      'where' => 'id=?',
      'params' => [$id],
    ]);
  }

  /**
   *
   */
  public function first() {
    return $this->find([
      'order' => 'created_at ASC',
    ]);
  }

  /**
   *
   */
  public function last() {
    return $this->find([
      'order' => 'created_at DESC',
    ]);
  }

  /**
   *
   */
  public function validate($record) {
    if (gettype($record) === 'array') {
      $record = new Record($record);
    }
    $this->runHook('before-validate', [$record]);
    $errors = [];
    $fields = $this->storage->getFields();
    foreach ($fields as $field) {
      $validateFn = $field->getValidateFn();
      if ($validateFn) {
        $error = $validateFn(
          $record->get($field->getAlias()),
          $field->getOptions(),
          $record,
          $this->connection
        );
        if ($error) {
          $errors[$field->getAlias()] = $error;
        }
      }
    }
    $this->runHook('after-validate', [$record, $errors]);
    return count($errors) > 0 ? $errors: null;
  }

  /**
   *
   */
  public function save($record, $validate=true) {
    if (gettype($record) === 'array') {
      $record = new Record($record);
    }
    $this->runHook('before-save', [$record, $validate]);
    if ($validate && $this->validate($record) !== null) {
      return false;
    }
    $recordTableName = $this->storage->getRecordTableName();
    $fields = $this->storage->getFields();
    $columns = [];
    $values = [];
    foreach ($fields as $field) {
      $fieldValue = $record->get($field->getAlias());
      if ($fieldValue) {
        $formatFn = $field->getFormatFn();
        if ($record->getId()) {
          $columns[] = "{$field->getAlias()}=?";
        } else {
          $columns[] = $field->getAlias();
        }
        $values[] = $formatFn ? $formatFn(
          $fieldValue,
          $field->getOptions(),
          $this->connection
        ): $fieldValue;
      }
    }
    if ($record->getId()) {
      $columns[] = 'updated_at=?';
      $values[] = time();
      $sqlQuery = "UPDATE {$recordTableName} SET " . join(',', $columns) . "
                   WHERE id='{$record->getId()}' LIMIT 1";
    } else {
      $columns[] = 'created_at';
      $values[] = time();
      $columns[] = 'updated_at';
      $values[] = time();
      $placeholders = array_fill(0, count($columns), '?');
      $sqlQuery = "INSERT INTO {$recordTableName}(".join(',', $columns).")
                   VALUES(".join(',', $placeholders).")";
    }
    if (count($values) === 0) {
      return false;
    }
    $sth = $this->connection->prepare($sqlQuery);
    $sth->execute($values);
    if ($record->getId()) {
      $record = $this->findById($record->getId());
    } else {
      $record = $this->findById($this->connection->lastInsertId());
    }
    $this->runHook('after-save', [$record]);
    return $record;
  }

  /**
   *
   */
  public function saveAll($records) {
    foreach ($records as $record) {
      if (! $this->save($record)) {
        return false;
      }
    }
    return true;
  }

  /**
   *
   */
  public function remove($record) {
    $recordTableName = $this->storage->getRecordTableName();
    $sqlQuery = "DELETE FROM {$recordTableName}
                 WHERE id=? LIMIT 1";
    $sth = $this->connection->prepare($sqlQuery);
    if ($sth->execute([$record->getId()]) === false) {
      return false;
    }
    return true;
  }

  /**
   *
   */
  public function removeAll() {
    foreach ($records as $record) {
      if (! $this->remove($record)) {
        return false;
      }
    }
    return true;
  }

  /**
   *
   */
  public function asArray($args=null) {
    return array_map(function ($item) {
      return $item->asArray();
    }, $this->findAll($args));
  }

  /**
   *
   */
  protected function makeQuery($args=null) {
    $where = isset($args['where']) ? trim($args['where']) : null;
    $order = isset($args['order']) ? trim($args['order']) : null;
    $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
    $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
    // prepare strings
    $whereString = empty($where) ? '' : "WHERE {$where}";
    $orderByString = empty($order) ? '' : "ORDER BY {$order}";
    $limitString = $limit > 0 ? "LIMIT {$offset}, {$limit}" : '';
    // prepare query
    $tableName = $this->storage->getRecordTableName();
    return "SELECT * FROM {$tableName} $whereString $orderByString $limitString";
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
}
