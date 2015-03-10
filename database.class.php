<?php
/**
 * Created by PhpStorm.
 * User: ershov-ilya
 * Website: ershov.pw
 * GitHub : https://github.com/ershov-ilya
 * Date: 07.02.2015
 * Time: 0:32
 */

class Database
{
    public $dbh; // Database handler
    public $cache;

    public static function test(){
        return "Database class: OK";
    }

    public function errors()
    {
        $info = $this->dbh->errorInfo();
        if(!empty($info[0])){
            if(DEBUG && !empty($info[2])) print $info[2]."\n";
            if(function_exists('logMessage')) logMessage($info[2]);
        }
    }

    public function __construct($input)
    {
        $this->cache = false;

        $input_type=gettype($input);
        switch($input_type)
        {
            case 'string':
                /* @var array $pdoconfig */
                require_once($input);
                extract($pdoconfig);
                break;
            case 'array':
                extract($input);
                break;
        }

        if(!(isset($dbtype) && isset($dbhost) && isset($dbname) && isset($dbuser) && isset($dbpass))) return false;
        try
        {
            /* @var PDO $DBH */
            // Save stream
            $this->dbh = $DBH = new PDO("$dbtype:host=$dbhost;dbname=$dbname" , $dbuser, $dbpass,
                array (PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
        }
        catch (PDOException $e ) {
            if(DEBUG) print 'Exception: ' . $e-> getMessage();
            logMessage('Exception: ' . $e-> getMessage());
            exit();
        }
    } // function __construct

    public function getOneSQL($sql)
    {
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        if(empty($rows)) return array();
        $result = $rows[0];
//        foreach($rows as $row){} // Изъятие из потока?
        $this->errors();
        return $result;
    }

    public function getOne($table, $id, $filter='', $id_field_name='id')
    {
        $sql = "SELECT ";
        if(empty($filter)) {
            $sql .= "*";
        }
        else{
            if(is_array($filter)){
                $sql.=implode(',',$filter);
            }
            else{
                $sql.=$filter;
            }
        }

        $sql .= " FROM `$table` WHERE `$id_field_name`='$id';";
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        if(empty($rows)) return array();
        $result = $rows[0];
//        foreach($rows as $row){} // Изъятие из потока?
        $this->errors();
        return $result;
    }

    public function get($sql)
    {
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        return $rows;
    }

    public function getTable($table, $columns='', $from=0, $limit=-1)
    {
        $page='';
        if(isset($limit) && $limit>=0) $page = "LIMIT $from, $limit";
        if(empty($columns)) $sql = "SELECT * FROM `$table` $page;";
        else{
            if(is_array($columns)) $columns = "`".implode("`,`",$columns)."`";
            $columns = preg_replace('/;/', '', $columns);
            $sql = "SELECT $columns FROM `$table` $page;";
        }
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $this->errors();
        return $rows;
    }

    public function getCount($sql)
    {
        // TODO: Неэкономичная функция, надо поправить
        $stmt = $this->dbh->query($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();
        $count = count($rows);
        $this->errors();
        return $count;
    }

    public function putOne($table, $data){
        $fields=array();
        $placeholders=array();
        foreach($data as $key => $val){
            $fields[]='`'.$key.'`';
            $placeholders[]=':'.$key;
        }
        $sql = "INSERT INTO `".$table."` (".implode(', ',$fields).") VALUES (".implode(', ',$placeholders).");";

        $stmt = $this->dbh->prepare($sql);
        foreach($data as $key => $val){
            $stmt->bindParam(':'.$key, $data[$key]);
        }
        $success = $stmt->execute();

        if(empty($success)) {
            if(DEBUG){
                print "ERROR:\n";
                print_r($stmt->errorInfo());
            }
            return false;
        }

        $lastID = $this->dbh->lastInsertId();
        return $lastID;
    }

    public function updateOne($table, $id, $data, $id_field_name='id'){
        $fields=array();
        $placeholders=array();
        foreach($data as $key => $val){
            $fields[]='`'.$key.'`';
            $placeholders[]=':'.$key;
        }
        $sql = "UPDATE `".$table."` SET ";

        $count = count($data);
        $i=0;
        foreach($data as $key => $val){
            $sql .= "`$key`=:$key";
            $i++;
            if($i<$count) $sql .= ",";
            $sql .= " ";
        }
        $sql .= " WHERE `$id_field_name`='".$id."';";

        $stmt = $this->dbh->prepare($sql);
        foreach($data as $key => $val){
            $stmt->bindParam(':'.$key, $data[$key]);
        }
        $success = $stmt->execute();
        if($success) return true;
        return false;
    }
} // class Database