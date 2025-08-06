<?php

namespace Drupal\flat_deposit\Plugin\media\Source;

use Drupal\media\Plugin\media\Source\File as CoreFile;
use Drupal\media\MediaTypeInterface;

/**
 * Custom File/Document media source to use filename extension for validation.
 *
 * @MediaSource(
 *   id = "flat_deposit_file",
 *   label = @Translation("Custom File (filename extension)"),
 *   description = @Translation("File media source that validates extension using filename."),
 *   allowed_field_types = {"file"},
 *   default_thumbnail_filename = "generic.png"
 * )
 */
class CustomFile extends CoreFile {

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints(MediaTypeInterface $media_type) {
    $constraints = [];
    $configuration = $this->getConfiguration();
    $allowed_extensions = $configuration['allowed_extensions'] ?? ['pdf', 'docx', 'txt', 'xlsx', 'pptx'];
    $constraints['CustomFileExtension'] = [
      'allowedExtensions' => $allowed_extensions,
    ];
    return $constraints;
  }

}
