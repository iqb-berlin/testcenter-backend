<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);
// TODO unit test


class DataCollection implements JsonSerializable {

    function __construct($initData) {

        $class = get_called_class();

        foreach ($initData as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            } else {
                throw new Exception("$class creation error:`$key` is unknown in `" . get_class($this) . "`");
            }
        }

        foreach ($this as $key => $value) {

            if ($value === null) {
                throw new Exception("$class creation error: `$key` is shall not be null after creation");
            }
        }
    }


    static function fromFile(string $path = null): DataCollection {

        if (!file_exists($path)) {
            throw new Exception("JSON file not found: `$path`");
        }

        $connectionData = JSON::decode(file_get_contents($path));

        $class = get_called_class();

        return new $class($connectionData);
    }


    public function jsonSerialize() {

        $jsonData = [];

        foreach ($this as $key => $value) {

            if (substr($key,0 ,1) != '_') {
                $jsonData[$key] = $value;
            }
        }

        return $jsonData;
    }
}