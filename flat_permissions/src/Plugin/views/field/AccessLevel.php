<?php

namespace Drupal\flat_permissions\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a custom field handler.
 *
 * @ViewsField("access_level")
 */
class AccessLevel extends FieldPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        // Leave this empty if you don't need to modify the query.
        // If needed, you can add custom SQL to the query here.
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values)
    {
        // Call your custom function and return the result.
        $output = get_access_level($values);
        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
        parent::buildOptionsForm($form, $form_state);

        // Add any custom settings to the field options form here, if needed.
    }
}
