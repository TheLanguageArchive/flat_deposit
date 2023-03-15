<?php

class CmdiTemplateData
{
    /**
     * @var $data cache of data
     */
    private static $data = null;

    public static function clear()
    {
        static::$data = null;
    }

    public static function getAvailableTemplates($profile, $component_id)
    {
        if (null === static::$data) {

            $user = \Drupal::currentUser();
            static::$data = \CmdiTemplateDb::all($profile, $user->id());
        }

        $options = [];

        foreach (static::$data as $row) {

            if ($row['component_id'] == $component_id) {
                $options[$row['id']] = $row['label'];
            }
        }

        return $options;
    }
}
