<?php

spl_autoload_register('autoload_cmdileaf');

function autoload_cmdileaf()
{

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiComposite');
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiComponent');
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiCreator');

}

/**
 * User: danrhe
 * Date: 25/01/2018
 * Time: 10:04
 */
class CmdiLeaf extends CmdiComponent{


    /**
     * CmdiComponent constructor.
     */
    public function __construct(){}


    public function setCmdiComponent(&$rootNewComponents, $component, $data){

        $id = (string)$component['id'];

        $name = (string)$component['name'];

        if (isset($data[$id])) {
            $componentData = $data[$id];

            foreach (array_keys($componentData) as $key){
                if (!is_numeric($key))
                    unset($componentData[$key]);

            }
        }


        if (isset($componentData)) {

            foreach ($componentData as $key => $value) {

                if ($value != null AND $value != ""){

                    $converted = $this->convertValue((string)$component->type, $value);
                    $child = $rootNewComponents->addChild($name, htmlspecialchars($converted));
                    foreach ($component->attribute as $attribute) {
                        $attributeId = (string) $attribute['id'];
                        $attributeName = (string) $attribute['name'];
                        if (isset($data[$attributeId][0])) {
                            $attributeData = (string) $data[$attributeId][0];
                            if ($attributeData != null and $attributeData != "") {
                                $child->addAttribute($attributeName, $attributeData);
                            }
                        }

                    }

                }
            }
        }



    }

}
