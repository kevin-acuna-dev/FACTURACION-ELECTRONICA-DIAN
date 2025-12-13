<?php

class Model {
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $result = $this->db->fetchOne($sql, [$id]);
        return $result ? $this->hydrate($result) : null;
    }
    
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $query = new QueryBuilder($this->table, $this);
        return $query->where($column, $operator, $value);
    }
    
    public function all() {
        $sql = "SELECT * FROM {$this->table}";
        $results = $this->db->fetchAll($sql);
        return array_map([$this, 'hydrate'], $results);
    }
    
    public function create($data) {
        $data = $this->filterFillable($data);
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $this->db->query($sql, $data);
        
        $id = $this->db->lastInsertId();
        return $this->find($id);
    }
    
    public function update($data) {
        $data = $this->filterFillable($data);
        $id = $this->{$this->primaryKey};
        
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $set = implode(', ', $set);
        
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        $this->db->query($sql, $data);
        
        return $this->find($id);
    }
    
    public function delete() {
        $id = $this->{$this->primaryKey};
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $this->db->query($sql, [$id]);
        return true;
    }
    
    public function hydrate($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }
    
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    public function toArray() {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if (!in_array($key, $this->hidden) && $key !== 'table' && $key !== 'primaryKey' && $key !== 'fillable' && $key !== 'hidden' && $key !== 'db') {
                $data[$key] = $value;
            }
        }
        return $data;
    }
    
    public function __get($name) {
        return isset($this->$name) ? $this->$name : null;
    }
    
    public function __set($name, $value) {
        $this->$name = $value;
    }
}

class QueryBuilder {
    private $table;
    private $model;
    private $wheres = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    
    public function __construct($table, $model) {
        $this->table = $table;
        $this->model = $model;
    }
    
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }
    
    public function join($table, $first, $operator, $second) {
        $this->joins[] = "JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }
    
    public function get() {
        $sql = "SELECT * FROM {$this->table}";
        
        foreach ($this->joins as $join) {
            $sql .= " {$join}";
        }
        
        if (!empty($this->wheres)) {
            $whereConditions = [];
            $params = [];
            foreach ($this->wheres as $where) {
                $whereConditions[] = "{$where['column']} {$where['operator']} ?";
                $params[] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }
        
        $db = Database::getInstance();
        $results = $db->fetchAll($sql, $params ?? []);
        return array_map(function($row) {
            $model = clone $this->model;
            return $model->hydrate($row);
        }, $results);
    }
    
    public function first() {
        $results = $this->limit(1)->get();
        return !empty($results) ? $results[0] : null;
    }
    
    public function count() {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $whereConditions = [];
            $params = [];
            foreach ($this->wheres as $where) {
                $whereConditions[] = "{$where['column']} {$where['operator']} ?";
                $params[] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $db = Database::getInstance();
        $result = $db->fetchOne($sql, $params ?? []);
        return (int)$result['count'];
    }
}

