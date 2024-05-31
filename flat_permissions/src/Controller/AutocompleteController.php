<?php

namespace Drupal\flat_permissions\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\user\Entity\User;


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
            $results = $query->execute()->fetchCol();

            foreach ($results as $match) {
                $matches[] = ['value' => $match, 'label' => $match];
            }
        } elseif ($field_name === 'users') {

            $query = $this->database->select('users_field_data', 'u')
                ->fields('u', ['name'])
                ->condition('u.name', '%' . $this->database->escapeLike($string) . '%', 'LIKE');

            $results = $query->execute()->fetchCol();

            foreach ($results as $match) {
                $matches[] = ['value' => $match, 'label' => $match];
            }
        }

        return new JsonResponse($matches);
    }

    public function autocompleteCheckAccess(AccountInterface $account)
    {
        return AccessResult::allowedif($account->hasPermission('use deposit module')); // TODO add permission
    }
}
