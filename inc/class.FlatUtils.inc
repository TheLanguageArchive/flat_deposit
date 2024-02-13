<?php

/**
 * Helper functions for flat deposit as a replacement for FlatTuque, as it is no longer needed
 * to look things up in Fedora.
 * 
 * Needs field_member_of and title_aggregated fields to be indexed, using the default_solr_index
 * 
 */
class FlatUtils
{
    /**
     * Performs a Mulgara sparql query to retrieve all children labels of a given fedora object.
     *
     * @param $pid string fedora ID of an object.
     * @return array Strings with pid
     */
    public static function getChildrenTitles($nid)
    {
        // Load the search index.
        $index = Index::load('default_solr_index');

        // Create a query.
        $query = $index->query();

        // Optionally, limit the retrieved fields to only the field you are interested in.
        //$query->fields([$field_name]);
        $query->addCondition('field_member_of', $nid);

        // Execute the query.
        $results = $query->execute();

        // Extract the node IDs from the results.
        $node_ids = [];
        foreach ($results->getResultItems() as $item) {
            // Get the item's ID, which corresponds to the node ID.
            $node_ids[] = $item->getId();
            $title_field = $item->getField('title_aggregated');
            $titles[] = $title_field ? $title_field->getValues()[0] : '';
        }

        return $titles;
    }


    /**
     * Gets RELS-EXT 'isPartOfCollection' property for a certain fedora object
     *
     * @param $fid fedora ID
     * @return bool|String value of the isMemberOfCollection property or false
     */
    public static function getIsPartOfCollection($fid)
    {
        //return false;
        $return_value = false;

        $mapper = \Drupal::service('islandora.entity_mapper');
        $path = $mapper->getFedoraPath($fid);
        if ($path) {
            $return_value = trim($path, '/');
        }

        /*         $fObj = islandora_object_load($fid);
        if ($fObj) {
            $results = $fObj->relationships->get(FEDORA_RELS_EXT_URI, 'isMemberOfCollection');
            $count = 0;
            foreach ($results as $result) {
                $value = $result['object']['value'];
                $count++;
            }

            if ($count == 1) {
                $return_value = $value;
            }
        } */
        return $return_value;
    }

    /**
     * Gets RELS-EXT 'isPartOfCollection' property for a certain fedora object
     *
     * @param $fid fedora ID
     * @return bool|String value of the isMemberOfCollection property or false
     */
    public static function getCModels($fedoraObject)
    {
        return [];
        $model_array = [];

        $cmodels = $fedoraObject->relationships->get('info:fedora/fedora-system:def/model#', 'hasModel');

        foreach ($cmodels as $cmodel) {
            $model_array[] = $cmodel['object']['value'];
        }
        return $model_array;
    }
}
