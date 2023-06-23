<?php

namespace Drupal\flat_deposit\StreamWrapper;

use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;

class ExternalStreamWrapper extends PrivateStream {

    /**
     * @return string
     */
    public function getDirectoryPath() {
        return \Drupal::config('flat_deposit.settings')->get('flat_deposit_general')['external'];
    }

    /**
     * Overrides getExternalUrl().
     *
     * Return the HTML URI of a private file.
     */
    // public function getExternalUrl() {

    //     $path = str_replace('\\', '/', $this->getTarget());
    //     return Url::fromRoute('flat_deposit.metadata_file', $path, ['absolute' => true]);
    // }

    /**
     * Overrides StreamWrapperInterface::rename.
     */
    // public function rename($from_uri, $to_uri) {
    //     return rename($this->getLocalPath($from_uri), $this->getLocalPath($to_uri));
    // }

    /**
     * Overrides StreamWrapperInterface::getLocalPath.
     */
    // protected function getLocalPath($uri = null) {

    //     if (!isset($uri)) {
    //         $uri = $this->uri;
    //     }
    //     $path = $this->getDirectoryPath() . '/' . $this->getTarget($uri);
    //     $realpath = realpath($path);

    //     if (!$realpath) {

    //         // This file does not yet exist.
    //         $realpath = realpath(dirname($path)) . '/' . drupal_basename($path);
    //     }

    //     $directory = realpath($this->getDirectoryPath());

    //     if (!$realpath || !$directory || strpos($realpath, $directory) !== 0) {
    //         return false;
    //     }

    //     return $realpath;
    // }
}
