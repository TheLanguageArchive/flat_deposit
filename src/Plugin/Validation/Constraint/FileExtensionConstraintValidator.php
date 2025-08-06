<?php

namespace Drupal\flat_deposit\Plugin\Validation\Constraint;

use Drupal\file\Plugin\Validation\Constraint\BaseFileConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Custom file extension constraint validator.
 * Always uses the entity filename rather than file URI, because migrated 
 * Fedora OBJ datastreams do no have a file extension as part of the stream-wrapped
 * flysystem URI.
 */
class FileExtensionConstraintValidator extends BaseFileConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {

    \Drupal::logger('flat_deposit')->notice('Custom file extension validator triggered.');

    $file = $this->assertValueIsFile($value);

    if (!$constraint instanceof FileExtensionConstraint) {
      throw new UnexpectedTypeException($constraint, FileExtensionConstraint::class);
    }

    $extensions = $constraint->extensions;
    $regex = '/\.(' . preg_replace('/ +/', '|', preg_quote($extensions)) . ')$/i';
    // Always use the filename for validation.
    $subject = $file->getFilename();

    if (!preg_match($regex, $subject)) {
      $this->context->addViolation($constraint->message, ['%files-allowed' => $extensions]);
    }
  }
}



