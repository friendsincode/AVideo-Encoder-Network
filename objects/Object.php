<?php
abstract class ObjectYPT
{

    abstract static protected function getTableName();
    abstract static protected function getSearchFieldsNames();
    private $fieldsName = array();

    protected function load($id)
    {
        $user = self::getFromDb($id);
        if (empty($user)) {
            return false;
        }
        foreach ($user as $key => $value) {
            $this->$key = $value;
        }
        return true;
    }


    function __construct($id)
    {
        if (!empty($id)) {
            // get data from id
            $this->load($id);
        }
    }

    static protected function getFromDb($id)
    {
        global $global;
        $id = intval($id);
        $sql = "SELECT * FROM ".static::getTableName()." WHERE  id = $id LIMIT 1";
        $global['lastQuery'] = $sql;
        $res = $global['mysqli']->query($sql);
        return ($res) ? $res->fetch_assoc() : false;
    }

    static function getAll()
    {
        global $global;
        $sql = "SELECT * FROM  ".static::getTableName()." WHERE 1=1 ";

        $sql .= self::getSqlFromPost();

        $global['lastQuery'] = $sql;
        $res = $global['mysqli']->query($sql);
        $rows = array();
        if (!$res) {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }


    static function getTotal()
    {
        //will receive
        //current=1&rowCount=10&sort[sender]=asc&searchPhrase=
        global $global;
        $sql = "SELECT id FROM  ".static::getTableName()." WHERE 1=1  ";

        $sql .= self::getSqlSearchFromPost();

        $global['lastQuery'] = $sql;
        $res = $global['mysqli']->query($sql);


        return $res->num_rows;
    }


    static function getSqlFromPost()
    {
        $sql = self::getSqlSearchFromPost();

        if (!empty($_POST['sort'])) {
            $orderBy = array();
            foreach ($_POST['sort'] as $key => $value) {
                $orderBy[] = " {$key} {$value} ";
            }
            $sql .= " ORDER BY ".implode(",", $orderBy);
        } else {
            //$sql .= " ORDER BY CREATED DESC ";
        }

        if (!empty($_POST['rowCount']) && !empty($_POST['current']) && $_POST['rowCount']>0) {
            $current = ($_POST['current']-1)*$_POST['rowCount'];
            $sql .= " LIMIT $current, {$_POST['rowCount']} ";
        } else {
            $_POST['current'] = 0;
            $_POST['rowCount'] = 0;
            $sql .= " LIMIT 50 ";
        }
        return $sql;
    }

    static function getSqlSearchFromPost()
    {
        $sql = "";
        if (!empty($_POST['searchPhrase'])) {
            $_GET['q'] = $_POST['searchPhrase'];
        }
        if (!empty($_GET['q'])) {
            global $global;
            $search = $global['mysqli']->real_escape_string($_GET['q']);

            $like = array();
            $searchFields = static::getSearchFieldsNames();
            foreach ($searchFields as $value) {
                $like[] = " {$value} LIKE '%{$search}%' ";
            }
            if (!empty($like)) {
                $sql .= ' AND (' . implode(' OR ', $like) . ')';
            } else {
                $sql .= ' AND 1=1 ';
            }
        }

        return $sql;
    }

    function save()
    {
        global $global;
        $fieldsName = $this->getAllFields();
        if (!empty($this->id)) {
            $sql = "UPDATE ".static::getTableName()." SET ";
            $fields = array();
            foreach ($fieldsName as $value) {
                if (strtolower($value) == 'created' ) {
                    // do nothing
                } elseif (strtolower($value) == 'modified' ) {
                    $fields[] = " {$value} = now() ";
                } else {
                    $fields[] = " {$value} = '{$this->$value}' ";
                }
            }
            $sql .= implode(", ", $fields);
            $sql .= " WHERE id = {$this->id}";
        } else {
            $sql = "INSERT INTO ".static::getTableName()." ( ";
            $sql .= implode(",", $fieldsName). " )";
            $fields = array();
            foreach ($fieldsName as $value) {
                if (strtolower($value) == 'created' || strtolower($value) == 'modified' ) {
                    $fields[] = " now() ";
                } elseif (!isset($this->$value)) {
                    $fields[] = " NULL ";
                } else {
                    $fields[] = " '{$this->$value}' ";
                }
            }
            $sql .= " VALUES (".implode(", ", $fields).")";
        }
        //echo $sql;
        $global['lastQuery'] = $sql;
        $insert_row = $global['mysqli']->query($sql);

        if (!$insert_row) {
            die($sql . ' Error : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return (empty($this->id)) ? $global['mysqli']->insert_id : $this->id;
    }

    private function getAllFields()
    {
        global $global, $mysqlDatabase;
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$mysqlDatabase}' AND TABLE_NAME = '".static::getTableName()."'";
        //echo $sql;
        $global['lastQuery'] = $sql;
        $res = $global['mysqli']->query($sql);
        $rows = array();
        if (!$res) {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row["COLUMN_NAME"];
        }
        return $rows;
    }

    function delete()
    {
        global $global;
        if (!empty($this->id)) {
            $sql = "DELETE FROM ".static::getTableName()." ";
            $sql .= " WHERE id = {$this->id}";
            $global['lastQuery'] = $sql;
            //error_log("Delete Query: ".$sql);
            return $global['mysqli']->query($sql);
        }
        error_log("Id for table ".static::getTableName()." not defined for deletion");
        return false;
    }
}
