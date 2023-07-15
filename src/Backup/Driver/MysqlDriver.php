<?php

namespace Guirong\Database\Backup\Driver;

use PDO;

// +--------------------------------------------------------------------------------------------------------------------------------------------------------------
// | [MySQL数据库驱动]
// +--------------------------------------------------------------------------------------------------------------------------------------------------------------
// | auth: guirong <15168272969@163.com>
// +--------------------------------------------------------------------------------------------------------------------------------------------------------------


class MysqlDriver
{
    protected $host = '';
    protected $user = '';
    protected $name = '';
    protected $pass = '';
    protected $port = '';
    protected $db;

    public function __construct($host = null, $user = null, $name = null, $pass = null, $port = 3306)
    {
        if ($host !== null) {
            $this->host = $host;
            $this->name = $name;
            $this->port = $port;
            $this->pass = $pass;
            $this->user = $user;
        }
        $this->connection();
        return $this;
    }

    /**
     * 连接mysql
     *
     * @return void
     */
    protected function connection(){
        if(!$this->db){
            $this->db = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->name . '; port=' . $this->port, $this->user, $this->pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $this->db->exec('SET NAMES "utf8mb4"');
        }
    }

    /**
     * 清除连接
     *
     * @return void
     */
    protected function clearConnection(){
        $this->db = null;
    }

    /**
     * 获取所有表名
     * @return array
     */
    public function listTableNames()
    {
        $list = [];
        $tables = $this->db->query("SHOW TABLES");
        foreach ($tables as $table) {
            $list[] = $table[0];
        }
        return $list;
    }
}
