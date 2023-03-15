<?php

namespace Drupal\flat_deposit\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CmdiController extends ControllerBase {

  public function saveAction(Request $request) {

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiTemplate/class.CmdiModalBuilder');

    $renderer = \Drupal::service('renderer');

    if (false === $request->isMethod('POST')) {

      $errorModal = \CmdiModalBuilder::error();

      return new JsonResponse([

        'type' => 'error',
        'modal' => $renderer->render($errorModal),
      ]);
    }

    $data = json_decode($request->request->all(), true);

    if (false === is_array($data) || !isset($data['cmdi_data'])) {

      $errorModal = \CmdiModalBuilder::error();

      // post data invalid
      return new JsonResponse([

        'type' => 'error',
        'modal' => $renderer->render($errorModal),
      ]);
    }

    $user = \Drupal::currentUser();
    $uid = $user->id();
    $cmdi_id = $data['cmdi_data']['cmdi_id'];
    $profile = $data['cmdi_data']['profile'];
    $label = $data['cmdi_data']['label'];
    $component_id = $data['cmdi_data']['component_id'];

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiTemplate/class.CmdiTemplateDb');

    $exists = \CmdiTemplateDb::exists($profile, $label, $component_id, $uid);

    if (true === $exists) {

      $confirmModal = \CmdiModalBuilder::confirm($cmdi_id);

      return new JsonResponse([

        'type' => 'exists',
        'cmdi_id' => $cmdi_id,
        'modal' => $renderer->render($confirmModal),
      ]);
    }

    $successModal = \CmdiModalBuilder::success();

    return new JsonResponse([

      'type' => 'new',
      'cmdi_id' => $cmdi_id,
      'modal' => $renderer->render($successModal),
    ]);
  }
}