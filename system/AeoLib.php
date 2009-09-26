<?php

/* Aeolus library */
class AeoLib
{
    private static $loadedClassFiles = array();

    /**
     * check dependency and load classes if necessary
     *
     */
    public static function depends($className)
    {
        if (is_array($className)) {
            foreach ($className as $class) {
                self::loadClass($class);
            }
        } else {
            self::loadClass($className);
        }
    }

    private static function loadClass($className)
    {
        if (empty($className)) {
            return;
        }

        $fileName = $className . '.php';

        if (! in_array($fileName, self::$loadedClassFiles)) {
            require $fileName;

            self::$loadedClassFiles[] = $fileName;
        }
    }
}

?>
