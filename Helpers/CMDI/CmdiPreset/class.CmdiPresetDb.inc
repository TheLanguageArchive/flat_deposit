<?php

class CmdiPresetDb
{
    /**
     * Fetching all presets
     *
     * @param string $filename
     * @param int    $uid user id
     *
     * @return boolean
     */
    public static function all($profile, $uid)
    {
        return \Drupal::database()->select('flat_cmdi_presets', 'fct')
            ->fields('fct')
            ->condition('profile', $profile, '=')
            ->condition('uid', $uid, '=')
            ->execute()
            ->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    }

    /**
     * Fetching specific preset
     *
     * @param string $id
     * @param int    $uid user id
     *
     * @return boolean
     */
    public static function fetch($id, $uid)
    {
        return \Drupal::database()->select('flat_cmdi_presets', 'fct')
            ->fields('fct')
            ->condition('id', $id, '=')
            ->condition('uid', $uid, '=')
            ->execute()
            ->fetchAssoc('id', PDO::FETCH_ASSOC);
    }

    /**
     * check if label for component preset already exists
     *
     * @param string $profile
     * @param string $label
     * @param string $component_id
     * @param string $uid
     *
     * @return boolean
     */
    public static function exists($profile, $label, $component_id, $uid)
    {
        try {

            $result = \Drupal::database()->select('flat_cmdi_presets', 'fct')
                ->fields('fct', ['id'])
                ->condition('profile', $profile)
                ->condition('label', $label)
                ->condition('component_id', $component_id)
                ->condition('uid', $uid)
                ->countQuery()
                ->execute()
                ->fetchField();

            return intval($result) > 0;
        } catch (Exception $e) {

            static::error($e);
            return false;
        }
    }

    /**
     * Find existing block
     *
     * @param string $profile
     * @param string $label
     * @param string $component_id
     * @param string $uid
     *
     * @return boolean
     */
    public static function find($profile, $label, $component_id, $uid)
    {
        try {

            return \Drupal::database()->select('flat_cmdi_presets', 'fct')
                ->fields('fct')
                ->condition('profile', $profile)
                ->condition('label', $label)
                ->condition('component_id', $component_id)
                ->condition('uid', $uid)
                ->execute()
                ->fetchAssoc();
        } catch (Exception $e) {

            static::error($e);
            return false;
        }
    }

    /**
     * save cmdi preset block to db
     *
     * @param string $profile
     * @param string $label
     * @param string $component_id
     * @param string $block
     * @param string $uid
     *
     * @return boolean
     */
    public static function save($profile, $label, $component_id, $block, $uid)
    {
        try {

            $record = static::find($profile, $label, $component_id, $uid);

            if (false === $record) {
                static::insert($profile, $label, $component_id, $block, $uid);
            } else {
                static::override($record['id'], $uid, $block);
            }

            return true;
        } catch (Exception $e) {

            static::error($e);
            return false;
        }
    }

    /**
     * Override existing preset block in db
     *
     * @param string $profile
     * @param string $label
     * @param string $component_id
     * @param string $block
     * @param string $uid
     *
     * @return boolean
     */
    public static function override($id, $uid, $block)
    {
        return \Drupal::database()->update('flat_cmdi_presets')
            ->fields([
                'block' => $block,
            ])
            ->condition('id', $id)
            ->condition('uid', $uid)
            ->execute();
    }

    /**
     * Insert preset record into db
     *
     * @param string $profile
     * @param string $label
     * @param string $component_id
     * @param string $block
     * @param string $uid
     *
     * @return boolean
     */
    public static function insert($profile, $label, $component_id, $block, $uid)
    {
        return \Drupal::database()->insert('flat_cmdi_presets')
            ->fields([
                'profile'      => $profile,
                'label'        => $label,
                'component_id' => $component_id,
                'block'        => $block,
                'uid'          => $uid,
            ])
            ->execute();
    }

    /**
     * Delete specific preset
     *
     * @param string $id
     * @param int    $uid user id
     *
     * @return boolean
     */
    public static function delete($id, $uid)
    {
        return \Drupal::database()->delete('flat_cmdi_presets')
            ->condition('id', $id, '=')
            ->condition('uid', $uid, '=')
            ->execute();
    }

    /**
     * db error was found, send drupal message to show what
     * went wrong
     *
     * @param Exception $e
     */
    public static function error(Exception $e)
    {
        \Drupal::messenger()->addError($e->getMessage());
        /*         if ($e->errorInfo[0] == '42P01') {
            \Drupal::messenger()->addError(t('Flat Cmdi Presets table not found. Try running <a href="@update_url">update.php</a>.', ['@update_url' => \Drupal\Core\Url::fromRoute('system.db_update')]));
        } else {
            \Drupal::messenger()->addError($e->getMessage());
        } */
    }
}
