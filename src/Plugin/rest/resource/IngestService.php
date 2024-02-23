<?php

/**
 * Ingest service
 */

namespace Drupal\flat_deposit\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\file\Entity\File;

/**
 * Provides a Ingest Service resource.
 *
 * @RestResource(
 * id = "ingest_service",
 * label = @Translation("Ingest Service"),
 *   uri_paths = {
 *     "canonical" = "/api/flat_deposit/ingest",
 *     "create" = "/api/flat_deposit/ingest",
 *   }
 * )
 */
class IngestService extends ResourceBase
{
    /**
     * Responds to entity GET requests.
     * @return \Drupal\rest\ResourceResponse
     */
    public function get()
    {
        $response = ['message' => 'Hello, this is a rest service'];
        return new ResourceResponse($response);
    }

    /**
     * 
     * @Post
     * @Permission("use deposit module");
     * @return \Drupal\rest\ResourceResponse
     */
    public function post($data)
    {

        // Access and validate data from the request body
        if (!isset($data['nid'])) {
            return new \Drupal\rest\ResourceResponse(['error' => 'Missing required parameter nid'], 400);
        }

        // Info from Post request
        $nid = $data['nid'];
        $loggedin_user = $data['loggedin_user'];
        $test = $data['test'];

        // transform parameter test
        $test = ($test == 'Validate bundle') ? TRUE : FALSE;

        // load node
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        // get owner name from node
        $sipOwner = \Drupal::entityTypeManager()->getStorage('user')->load($node->getOwnerId());
        $sipOwnerName = $sipOwner->getAccountName();

        // get SIP type
        $nodeType = $node->getType();
        if ($nodeType == 'flat_bundle') {
            $sipType = 'Bundle';
        } elseif ($nodeType == 'flat_collection') {
            $sipType = 'Collection';
        }

        // get full record cmdi file path from node file field
        $cmdi = $node->get('flat_cmdi_file')->target_id;
        if ($cmdi) {
            $cmdi_file = File::load($cmdi);
            $cmdi_url = $cmdi_file->getFileUri();
        } else {
            $cmdi_url = '';
        }

        // get fedora ID of parent by loading node with node-id 'flat_parent_nid'
        $collection_nid = $node->get('flat_parent_nid_bundle')->value;

        //$collection_nid = $node->flat_parent_nid->value;
        $collection_node = \Drupal::entityTypeManager()->getStorage('node')->load($collection_nid);
        // @TODO get actual flat_fid once it is added as a property of the collection node
        //$collection_fid = $collection_node->get('flat_fid')->value;
        $collection_fid = "lat:12345";

        // instantiate client
        module_load_include('php', 'flat_deposit', 'Helpers/IngestService/IngestClient');

        try {
            $ingest_client = new \IngestClient($sipType, $sipOwnerName, $cmdi_url, $collection_fid, $test);
        } catch (\IngestServiceException $exception) {
            $response = new \Drupal\rest\ResourceResponse(['error' => 'Ingest client operation failed'], 400); // Bad Request
            return $response;
        }

        // set ingest parameters
        $info['loggedin_user'] = $loggedin_user;
        $info['nid'] = $nid;

        $try = $ingest_client->requestSipIngest($info);

        $response = new ResourceResponse();
        $response->setStatusCode(201); // Created (resource created successfully)
        return $response;
    }
}
