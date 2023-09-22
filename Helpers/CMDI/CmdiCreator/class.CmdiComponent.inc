<?php

spl_autoload_register('autoload_cmdicomponent');

function autoload_cmdicomponent()
{

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiComposite');
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiLeaf');
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiComponent');

}


/**
 * Created by PhpStorm.
 * User: danrhe
 * Date: 25/01/2018
 * Time: 10:04
 */
abstract class CmdiComponent
{

    protected $cmdiComponent;


    /**
     * CmdiComponent constructor.
     */
    public function __construct(){}

    abstract public function setCmdiComponent(&$rootNewComponents, $component, $data);



    protected function convertValue($type, $value){

        switch ($type){
            // case 'date':
            //     $converted = $value;
            //     $month = (strlen($value['month']) == 2 ) ? $value['month'] : '0'. $value['month'];
            //     $day = (strlen($value['day']) == 2 ) ? $value['day'] : '0'. $value['day'];
            //     $date = $value['year'] . '-' . $month . '-' . $day;
            //     $converted = $date;
            //     break;
            default :
                $converted = $value;
        }

        return $converted;

    }

}
