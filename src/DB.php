<?php


namespace chatApp;
use PDO;

class DB
{
    private static object|null $instance = null;
    private $pdo,
        $query,
        $error = false,
        $results,
        $count = 0;

    private function __construct() {
        do {
            //Datenbankverbindung aufbauen
            try {
                $host = 'localhost';
                $user = 'root';
                $password = '';
                $dbname = '1337chat';

                // DSN Data Source Name für PDO
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";

                //neue PDO Instanz erzeugen
                $this->pdo = new PDO($dsn, $user, $password);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                //$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                $this->error = false;
            }
            catch(\PDOException $e) {
                echo "Datenbankfehler: " . $e->getMessage() . PHP_EOL;
                $this->error = true;
            }
            sleep(1);
        } while($this->error);

    }

    public static function getInstance(): DB|null
    {
        if(!isset(self::$instance)) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->error;
    }

    /**
     * Funktion für universelle Abfragen in der Datenbank.
     * @param string $sql SQL Query, der ausgeführt werden soll
     * @param array $params Assoziatives Array mit Parametern
     *                      z.B.: ["id" => 3]
     * @return $this
     */
    public function query(string $sql, array $params = []): static
    {
        $this->error = false;
        $this->results = [];

        if($this->query = $this->pdo->prepare($sql)) {
            if(count($params)) {

                foreach($params as $param => $value) {
                    //um zwischen den verschiedenen datentypen zu entscheiden, sonst wird alles als string gebinded, das gibt zb. bei LIMIT fehler
                    switch (gettype($value)) {
                        case "integer":
                            $this->query->bindValue($param, $value, PDO::PARAM_INT);
                            break;
                        default:
                            $this->query->bindValue($param, $value);
                    }
                }
            }

            if($this->query->execute()) {
                $this->count = $this->query->rowCount();
                $this->results = $this->query->fetchAll();
            }
            else {
                $this->error = $this->query->errorInfo();
                echo "Datenbankfehler: " . $this->query->errorCode() . " " . $this->query->errorInfo()[2];
            }
        }
        return $this;
    }

    /**
     * Fügt Daten der angegebenen Tabelle hinzu.
     * @param string $table
     * @param array $content Daten als assoziatives Array
     *                       z.B. ["id" => 3, "user" => "harry"]
     * @return bool true, wenn bei der Ausführung kein Fehler auftritt
     */
    public function insert(string $table, array $content = []): bool
    {
        // wenn keine daten da sind zum einfügen, return false
        if(!count($content)) {
            return false;
        }
        // jeden key im assoc array mit einem doppelpunkt versehen
        // und als separates array speichern
        $keys = array_keys($content);
        $keys_prefixed = [];
        foreach ($keys as $key) {
            $keys_prefixed[] = ":" . $key;
        }
        // sql string zusammenbauen
        $sql = "INSERT INTO `$table` (" . implode(",", $keys) . ") VALUES (" . implode(",", $keys_prefixed) . ")";
        // query ausführen und prüfen ob dabei fehler auftritt
        if(!$this->query($sql, $content)->isError()) {
            return true;
        }
        return false;
    }

    /**
     * Aktualisiert Daten in der angegebenen Tabelle.
     * @param string $table
     * @param array $content Daten als assoziatives Array
     *                       z.B. ["status" => 1]
     * @param array $where  (optional) WHERE Klausel als array
     *                      z.B. ["uuid" => "u1337uwu4711"]
     * @return bool true, wenn bei der Ausführung kein Fehler auftritt
     */
    public function update(string $table, array $content = [], array $where = []): bool
    {
        // wenn keine daten da sind zum updaten, return false
        if(!count($content)) {
            return false;
        }

        // SET String für UPDATE query zusammenbauen
        $updateString = '';
        $i = 1;
        foreach ($content as $key => $value) {
            $updateString .= "`$key` = :$key";
            if($i < count($content)) {
                $updateString .= ', ';
            }
            $i++;
        }

        $whereString = '';

        if(!empty($where)){
            $whereString = 'WHERE ';
            $whereKeys = array_keys($where);
            $i = 1;
            foreach ($whereKeys as $key) {
                $whereString .= "`$key` = :$key";

                if($i < count($where)) {
                    $whereString .= ' AND ';
                }
                $i++;
            }
            // parameter für query zusammenfügen in ein array
            $content = array_merge($content, $where);
        }

        // sql string zusammenbauen
        $sql = "UPDATE `$table` SET $updateString $whereString";

        // query ausführen und prüfen ob dabei fehler auftritt
        if(!$this->query($sql, $content)->isError()) {
            return true;
        }
        return false;
    }

    public function results() {
        if(empty($this->results)) {
            return [];
        }
        /*
        else if(count($this->results) === 1) {
            return $this->results[0];
        }
        */
        return $this->results;

    }

    public function get($table, $where = array()): bool|static
    {
        $whereString = '';
        if(!empty($where)) {
            $whereString = 'WHERE ';
            $whereKeys = array_keys($where);
            $i = 1;
            foreach ($whereKeys as $key) {
                $whereString .= "`$key` = :$key";

                if ($i < count($where)) {
                    $whereString .= ' AND ';
                }
                $i++;
            }
        }
        $sql = "SELECT * FROM $table $whereString";
        if(!$this->query($sql, $where)->isError()) {
            return $this;
        }
        return false;
    }

    public function getMessage($where = []) {
        if(count($this->get("messages", $where)->results()) === 1) {
            return $this->results()[0];
        }
        return false;
    }

}