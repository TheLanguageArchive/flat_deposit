<?php

/**
 * Created by PhpStorm.
 * User: danrhe
 * Date: 08/06/2017
 * Time: 15:13
 */
class FlatNode
{
    public static function transliteration_clean_filename($filename, $source_langcode = 'en')
    {
        if (is_array($filename)) {
            foreach ($filename as $key => $value) {
                $filename[$key] = self::transliteration_clean_filename($value, $source_langcode);
            }
            return $filename;
        }
        $filename = self::transliteration_get($filename, '', $source_langcode);
        // Replace whitespace.
        $filename = str_replace(' ', '_', $filename);
        // Remove remaining unsafe characters.
        $filename = preg_replace('![^0-9A-Za-z_.-]!', '', $filename);
        // Remove multiple consecutive non-alphabetical characters.
        $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/', '\\1\\2\\3', $filename);
        // Force lowercase to prevent issues on case-insensitive file systems.
        // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// if (variable_get('transliteration_file_lowercase', TRUE)) {
//             $filename = strtolower($filename);
//         }
        $transliteration_file_lowercase = \Drupal::config('flat_deposit.settings')->get('transliteration_file_lowercase') ?? true;
        if ($transliteration_file_lowercase) {
            $filename = strtolower($filename);
        }

        return $filename;
    }
    public static function transliteration_get($text, $unknown = '?', $source_langcode = 'en')
    {
        return \Drupal::service('transliteration')->transliterate($text, $source_langcode, $unknown);
    }
}
