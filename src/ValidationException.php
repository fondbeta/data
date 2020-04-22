<?php

namespace atk4\data;

/**
 * Class description?
 */
class ValidationException extends Exception
{
    /** @var array Array of errors */
    public $errors = [];

    /**
     * Constructor.
     *
     * @param array $errors Array of errors
     * @param mixed $intent
     *
     * @return \Exception
     */
    public function __construct($errors, $model = null, $intent = null)
    {
        $this->errors = $errors;

        if (count($errors) > 1) {
            return parent::__construct([
                'Multiple unhandled validation errors',
                'errors' => $errors,
                'intent' => $intent,
                'model'  => $model,
            ]);
        } else {
            $error = reset($errors);
            $field = key($errors);

            return parent::__construct([
                $error,
                'field' => $field,
                'model' => $model,
            ]);
        }
    }
}
