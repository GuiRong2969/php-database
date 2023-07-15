<?php

namespace Guirong\Database\Backup;

use Exception;
use Guirong\Database\Backup\Driver\MysqlDriver;
use Guirong\Database\Backup\Traits\CallResultTrait;
use ZipArchive;

// +--------------------------------------------------------------------------------------------------------------------------------------------------------------
// | [MySQL数据库备份]
// +--------------------------------------------------------------------------------------------------------------------------------------------------------------
// | auth: guirong <15168272969@163.com>
// +--------------------------------------------------------------------------------------------------------------------------------------------------------------


class MysqlBackup extends MysqlDriver
{
    protected $backupFilename = '';
    protected $tables = ['*'];
    protected $ignoreTables = [];
    protected $puck = false;
    protected $recoveryFile;
    protected $recoveryTables = [];
    protected $recoverySql = '';

    use CallResultTrait;

    /**
     * 设置备份表
     * @param string $table
     * @return $this
     */
    public function setTable($table)
    {
        if ($table) {
            $this->tables = is_array($table) ? $table : explode(',', $table);
        }
        return $this;
    }

    /**
     * 设置忽略备份的表
     * @param mix $table
     * @return $this
     */
    public function setIgnoreTable($table)
    {
        if ($table) {
            $this->ignoreTables = is_array($table) ? $table : explode(',', preg_replace('/\s+/', '', $table));
        }
        return $this;
    }

    /**
     * 设置备份文件名
     * @param string $filename
     * @return $this
     */
    public function setBackupFilename($filename)
    {
        if ($filename != '') {
            $this->backupFilename = $filename;
        }
        return $this;
    }

    /**
     * 设置压缩
     * @param boolean $pack
     * @return $this
     */
    public function setPack($pack = false)
    {
        $this->puck = $pack;
        return $this;
    }

    /**
     * 执行备份
     * @param string $backUpdir 存储目录
     * @return void
     */
    public function backup($backUpdir = 'download/')
    {
        try {
            $sql = $this->getSqldump();
            $date = date('YmdHis');
            if (!is_dir($backUpdir)) {
                @mkdir($backUpdir, 0755);
            }
            $name = $this->backupFilename ?: "mysql-{$this->host}@{$this->name}-{$date}";
            if ($this->puck) {
                if (!class_exists('ZipArchive')) {
                    throw new Exception("服务器缺少php-zip组件，无法进行备份操作");
                }
                $filename = $backUpdir . $name . ".zip";
                $zip = new ZipArchive();
                if ($zip->open($filename, ZIPARCHIVE::CREATE) !== true) {
                    throw new Exception("Could not open <$filename>\n");
                }
                $zip->addFromString($name . ".sql", $sql);
                $zip->close();
            } else {
                $filename = $backUpdir . $name . ".sql";
                file_put_contents($filename, $sql);
            }
            $this->setResponse($filename);
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
        return $this->judgeTrue();
    }

    /**
     * 获取导出sql
     * @return string
     */
    public function getSqldump()
    {
        # COUNT
        $ct = 0;
        # CONTENT
        $sqldump = "/*\n";
        # COPYRIGHT & OPTIONS
        $sqldump .= "SQL Dump by Rong Gui\n";
        $sqldump .= "version 1.0\n";
        $sqldump .= "SQL Dump created: " . date('F jS, Y \@ H:i a') . "\n";
        $sqldump .= "*/\n\n";
        $sqldump .= "SET NAMES utf8mb4;\n";
        $sqldump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        $tables = $this->db->query("SHOW FULL TABLES WHERE Table_Type != 'VIEW'");
        # LOOP: Get the tables
        foreach ($tables as $table) {
            // 忽略表
            if (in_array($table[0], $this->ignoreTables) || (!in_array('*', $this->tables) && !in_array($table[0], $this->tables))) {
                continue;
            }
            # COUNT
            $ct++;
            /** ** ** ** ** **/
            # DATABASE: Count the rows in each tables
            $count_rows = $this->db->prepare("SELECT * FROM `" . $table[0] . "`");
            $count_rows->execute();
            $c_rows = $count_rows->columnCount();
            # DATABASE: Count the columns in each tables
            $count_columns = $this->db->prepare("SELECT COUNT(*) FROM `" . $table[0] . "`");
            $count_columns->execute();
            $c_columns = $count_columns->fetchColumn();
            /** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** ** **/
            # MYSQL DUMP: Remove tables if they exists
            $sqldump .= "-- table $table[0] start\n\n";
            $sqldump .= "-- ----------------------------------\n";
            $sqldump .= "-- Remove the table if it exists\n";
            $sqldump .= "-- ----------------------------------\n\n";
            $sqldump .= "DROP TABLE IF EXISTS `" . $table[0] . "`;\n\n";
            /** ** ** ** ** **/
            # MYSQL DUMP: Create table if they do not exists
            $sqldump .= "-- ----------------------------------\n";
            $sqldump .= "-- Create the table if it not exists\n";
            $sqldump .= "-- ----------------------------------\n\n";
            # LOOP: Get the fields for the table
            foreach ($this->db->query("SHOW CREATE TABLE `" . $table[0] . "`") as $field) {
                $sqldump .= str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $field['Create Table']);
            }
            # MYSQL DUMP: New rows
            $sqldump .= ";\n\n\n";
            /** ** ** ** ** **/
            # CHECK: There are one or more columns
            if ($c_columns != 0) {
                # MYSQL DUMP: List the data for each table
                $sqldump .= "-- ----------------------------------\n";
                $sqldump .= "-- List the data for the table\n";
                $sqldump .= "-- ----------------------------------\n";
                $sqldump .= "-- Insert the following data into the table start\n\n";
                # MYSQL DUMP: Insert into each table
                $insertSql = "INSERT INTO `" . $table[0] . "` (";
                // $sqldump .= "INSERT INTO `" . $table[0] . "` (";
                # ARRAY
                $rows = [];
                $numeric = [];
                # LOOP: Get the tables
                foreach ($this->db->query("DESCRIBE `" . $table[0] . "`") as $row) {
                    $rows[] = "`" . $row[0] . "`";
                    $numeric[] = (bool)preg_match('#^[^(]*(BYTE|COUNTER|SERIAL|INT|LONG$|CURRENCY|REAL|MONEY|FLOAT|DOUBLE|DECIMAL|NUMERIC|NUMBER)#i', $row[1]);
                }
                $insertSql .= implode(', ', $rows);
                $insertSql .= ") VALUES (";
                # COUNT
                $c = 0;
                # LOOP: Get the tables
                foreach ($this->db->query("SELECT * FROM `" . $table[0] . "`") as $data) {
                    # COUNT
                    $c++;
                    /** ** ** ** ** **/
                    $sqldump .= $insertSql;
                    # ARRAY
                    $cdata = [];
                    # LOOP
                    for ($i = 0; $i < $c_rows; $i++) {
                        $value = $data[$i];

                        if (is_null($value)) {
                            $cdata[] = "NULL";
                        } elseif ($numeric[$i]) {
                            $cdata[] = $value;
                        } else {
                            $cdata[] = $this->db->quote($value);
                        }
                    }
                    $sqldump .= implode(', ', $cdata);
                    $sqldump .= ");\n";
                }
            }
            $sqldump .= "-- table $table[0] end\n\n";
        }

        $sqldump .= "\n\n\n";
        // Backup views
        $tables = $this->db->query("SHOW FULL TABLES WHERE Table_Type = 'VIEW'");
        # LOOP: Get the tables
        foreach ($tables as $table) {
            // 忽略表
            if (in_array($table[0], $this->ignoreTables)) {
                continue;
            }
            foreach ($this->db->query("SHOW CREATE VIEW `" . $table[0] . "`") as $field) {
                $sqldump .= "-- table $table[0] start\n\n";
                $sqldump .= "-- ----------------------------------\n";
                $sqldump .= "-- Remove the view if it exists\n";
                $sqldump .= "-- ----------------------------------\n\n";
                $sqldump .= "DROP VIEW IF EXISTS `{$field[0]}`;\n\n";
                $sqldump .= "-- ----------------------------------\n";
                $sqldump .= "-- Create the view if it not exists\n";
                $sqldump .= "-- ----------------------------------\n\n";
                $sqldump .= "{$field[1]};\n\n";
                $sqldump .= "-- table $table[0] end\n\n";
            }
        }
        return $sqldump;
    }

    /**
     * 解析sql恢复文件并拆解
     * @param string $recoverySql sql内容
     * @return array
     */
    protected function resloveSqlToList($recoverySql)
    {
        $splcing = '-- ' . md5(md5(microtime()));
        $sql = str_replace(
            [
                "-- ----------------------------------\n-- Remove the table if it exists\n-- ----------------------------------",
                "-- ----------------------------------\n-- Create the table if it not exists\n-- ----------------------------------",
                "-- ----------------------------------\n-- List the data for the table\n-- ----------------------------------",
                "-- ----------------------------------\n-- Remove the view if it exists\n-- ----------------------------------",
                "-- ----------------------------------\n-- Create the view if it not exists\n-- ----------------------------------",
            ],
            [$splcing, $splcing, $splcing, $splcing, $splcing],
            $recoverySql
        );
        $sqlList = explode($splcing, $sql);
        return $sqlList;
    }

    /**
     * 设置需要恢复的表
     * @param mix $table
     * @return $this
     */
    public function setRecoveryTable($table)
    {
        if ($table) {
            $this->recoveryTables = is_array($table) ? $table : explode(',', preg_replace('/\s+/', '', $table));
        }
        return $this;
    }

    /**
     * 设置恢复文件
     * @param string $filepath
     * @return $this
     */
    public function setRecoveryFile($filepath)
    {
        $this->recoveryFile = $filepath;
        return $this;
    }

    /**
     * 正则匹配出所有表名
     * @return array
     */
    public function getRecoveryTables()
    {
        $preg = '|CREATE TABLE IF NOT EXISTS `([^^]*?)`|u';
        preg_match_all($preg, $this->recoverySql, $res);
        return $res[1];
    }

    /**
     * 获取当前数据表的备份sql
     * @param string $table
     * @return string
     */
    protected function getRecoveryTableSql($table)
    {
        $start = mb_stripos($this->recoverySql, "-- table $table start");
        $end = mb_stripos($this->recoverySql, "-- table $table end");
        return mb_substr($this->recoverySql, $start, $end - $start);
    }

    /**
     * 判断是否insert语句片段
     * @param string $sql
     * @return boolean
     */
    protected function isInsertSql($sql)
    {
        return substr(ltrim($sql), 0, 49) == '-- Insert the following data into the table start';
    }

    /**
     * 导入sql语句
     * @param string $sql
     * @return void
     */
    protected function execSql($sql)
    {
        if (trim($sql) != '') {
            $this->db->exec($sql);
        }
    }

    /**
     * 数据恢复导入
     * @return boolean
     */
    public function recovery()
    {
        try {
            $this->recoverySql = file_get_contents($this->recoveryFile);
            if ($this->recoverySql == '') {
                throw new Exception('请先设置正确的恢复文件,method:' . (__CLASS__) . '->setRecoveryFile($filepath)');
            }
            //调整内存限制
            $this->setMaxAallowedPacket();
            $this->execSql(
                $this->getRecoverySql()
            );
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
        return $this->judgeTrue();
    }

    /**
     * 获取导入的sql
     *
     * @return string
     */
    protected function getRecoverySql()
    {
        if (!$this->recoveryTables) {
            return $this->recoverySql;
        }
        $list = [];
        foreach ($this->recoveryTables as $table) {
            $tableSql = $this->getRecoveryTableSql($table);
            if ($table != '') {
                $list[] = $tableSql;
            }
        }
        $this->recoverySql = $list ? implode(PHP_EOL, $list) : '';
        return $this->recoverySql;
    }

    /**
     * 设置mysql单次可执行sql的最大内存
     *
     * @return void
     */
    protected function setMaxAallowedPacket()
    {
        $list = $this->db->query('SELECT @@global.max_allowed_packet');
        $resetList = [];
        foreach ($list as $key => $value) {
            $resetList[$key] = $value;
        }
        $filesize = filesize($this->recoveryFile);
        if (isset($resetList[0]['@@global.max_allowed_packet']) && $filesize >= $resetList[0]['@@global.max_allowed_packet']) {
            $this->db->exec('SET @@global.max_allowed_packet = ' . ($filesize + 1024));
            //throw new Exception('备份文件超过配置max_allowed_packet大小，请修改Mysql服务器配置');

            //修改配置后，必须清除并重连一次mysql
            $this->clearConnection();
            $this->connection();
        }
    }
}
