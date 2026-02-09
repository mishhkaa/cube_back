<?php

namespace App\Classes;

class Csv
{
    public function __construct(protected $separator = ',', protected $enclosure = '"')
    {
    }

    public function buildRows(array $rows): string
    {
        $temp = tmpfile();
        foreach ($rows as $row){
            if ($row && is_array($row)){
                fputcsv($temp, array_values($row), $this->separator, $this->enclosure, "\\");
            }
        }

        $content = file_get_contents(stream_get_meta_data($temp)['uri']);

        fclose($temp);

        return $content;
    }
}
