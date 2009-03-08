<?php

    /*
     * Model class
     */

    class AeoModel
    {
        private static $driver = null;

        private $data = array(
            'affected' => 0,
            'lastInsertId' => 0,
            'set' => array()
        );

        function __construct()
        {
        }

        public final function insert($sql)
        {
            $driver = self::getDriver();

            if ($driver->query($sql)) {
                $this->data['affected'] = mysql_affected_rows();
                $this->data['lastInsertId'] = mysql_insert_id();
            }

            return $this->data;
        }

        public final function update($sql)
        {
            $driver = self::getDriver();

            if ($driver->query($sql))
                $this->data['affected'] = mysql_affected_rows();

            return $this->data;
        }

        public final function delete($sql)
        {
            $driver = self::getDriver();

            if ($driver->query($sql))
                $this->data['affected'] = mysql_affected_rows();

            return $this->data;
        }

        public final function select($sql)
        {
            $driver = self::getDriver();

            if ($result = $driver->query($sql)) {
                $this->data['affected'] = mysql_num_rows($result);
                if ($this->data['affected'] > 0) {
                    /* Fetch data */
                    while ($dataset = mysql_fetch_assoc($result))
                    $this->data['set'][] = $dataset;

                    $dataset = null;
                }
            }

            return $this->data;
        }

        public final function escape($v)
        {
            return mysql_real_escape_string($v,self::$driver->get_link());
        }

        private static final function getDriver()
        {
            if (null == self::$driver) {
                require A_PREFIX.'config/system/database/driver.php';

                Aeolus::loadClass("database/$driver");
                self::$driver = new $driver();
            }

            return self::$driver;
        }
    }
?>
