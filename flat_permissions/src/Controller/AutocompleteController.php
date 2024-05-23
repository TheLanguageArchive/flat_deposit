<?php

namespace Drupal\flat_permissions\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class AutocompleteController extends ControllerBase
{

    protected $database;

    public function __construct(Connection $database)
    {
        $this->database = $database;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database')
        );
    }

    public function handleAutocomplete(Request $request, $field_name)
    {
        $matches = [];
        $string = $request->query->get('q');

        if ($field_name === 'mime_type') {

            $query = $this->database->select('media__field_mime_type', 'mfmt')
                ->fields('mfmt', ['field_mime_type_value']);

            $query->condition('mfmt.field_mime_type_value', '%' . $this->database->escapeLike($string) . '%', 'LIKE')
                ->range(0, 10)
                ->distinct(TRUE);
            $results = $query->execute()->fetchAll();

            foreach ($results as $row) {
                $matches[] = ['value' => $row->field_mime_type_value, 'label' => $row->field_mime_type_value];
            }
        } elseif ($field_name === 'users') {
            // @TODO implement this
            $entity_query = \Drupal::entityQuery('user');
            $matches = $entity_query->execute();
        }

        return new JsonResponse($matches);
    }

    public function autocompleteCheckAccess(AccountInterface $account)
    {
        return AccessResult::allowedif($account->hasPermission('use deposit module')); // TODO add permission
    }
}
