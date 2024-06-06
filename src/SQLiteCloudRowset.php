<?php

namespace SQLiteCloud;

class SQLiteCloudRowset
{
    public $nrows = 0;
    public $ncols = 0;
    public $version = 0;
    public $data = null;

    // version 2 only
    public $colname = null;
    public $decltype = null;
    public $dbname = null;
    public $tblname = null;
    public $origname = null;
    public $notnull = null;
    public $prikey = null;
    public $autoinc = null;

    private function computeIndex($row, $col)
    {
        if ($row < 0 || $row >= $this->nrows) {
            return -1;
        }
        if ($col < 0 || $col >= $this->ncols) {
            return -1;
        }
        return $row * $this->ncols + $col;
    }

    public function value($row, $col)
    {
        $index = $this->computeIndex($row, $col);
        if ($index < 0) {
            return null;
        }
        return $this->data[$index];
    }

    public function name($col)
    {
        if ($col < 0 || $col >= $this->ncols) {
            return null;
        }
        return $this->colname[$col];
    }

    public function dump()
    {
        print("version: {$this->version}\n");
        print("nrows: {$this->nrows}\n");
        print("ncols: {$this->ncols}\n");

        print("colname: ");
        print_r($this->colname);
        print("\n");

        if ($this->version == 2) {
            print("decltype: ");
            print_r($this->decltype);
            print("\n");

            print("dbname: ");
            print_r($this->dbname);
            print("\n");

            print("tblname: ");
            print_r($this->tblname);
            print("\n");

            print("origname: ");
            print_r($this->origname);
            print("\n");

            print("notnull: ");
            print_r($this->notnull);
            print("\n");

            print("prikey: ");
            print_r($this->prikey);
            print("\n");

            print("autoinc: ");
            print_r($this->autoinc);
            print("\n");
        }

        if ($this->data && count($this->data) > 0) {
            print("data: ");
            print_r($this->data);
            print("\n");
        }
    }
}
