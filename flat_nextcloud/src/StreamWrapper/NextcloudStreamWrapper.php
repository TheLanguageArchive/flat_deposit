<?php

namespace Drupal\flat_nextcloud\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\Url;

class NextcloudStreamWrapper extends LocalStream
{

  protected $uri;

  public function getName()
  {
    return 'Nextcloud Stream';
  }

  public function getDescription()
  {
    return 'Streamwrapper for Nextcloud files.';
  }

  public function getExternalUrl()
  {
    return "";
  }

  /**
   * @return string
   */
  public function getDirectoryPath()
  {
    return \Drupal::config('flat_nextcloud.settings')->get('data_dir');
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
  /*     protected function getLocalPath($uri = NULL) {
        if (!isset($uri)) {
          $uri = $this->uri;
        }
        $path = $this
          ->getDirectoryPath() . $this
          ->getTarget($uri);
      
        $realpath = realpath($path);
        if (!$realpath) {
      
          // This file does not yet exist.
          $realpath = realpath(dirname($path)) . '/' . \Drupal::service('file_system')
            ->basename($path);
        }
        $directory = realpath($this
          ->getDirectoryPath());
        if (!$realpath || !$directory || !str_starts_with($realpath, $directory)) {
          return FALSE;
        }
        return $realpath;
      } */
}
