<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class Role extends Model {
    protected $table = 'roles';
    protected $fillable = ['role_name', 'description'];
}

