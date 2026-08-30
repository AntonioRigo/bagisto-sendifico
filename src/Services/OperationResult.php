<?php

namespace Tapstar\Sendifico\Services;

class OperationResult
{
    /**
     * Create a new result instance.
     *
     * @return void
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly bool $needsTerritory = false,
        public readonly array $data = [],
    ) {}

    /**
     * A successful operation.
     *
     * @return self
     */
    public static function success(string $message, array $data = [])
    {
        return new self(true, $message, false, $data);
    }

    /**
     * A failed operation with an actionable message.
     *
     * @return self
     */
    public static function failure(string $message)
    {
        return new self(false, $message);
    }

    /**
     * The address needs a manual territory confirmation before a draft can exist.
     *
     * @return self
     */
    public static function needsTerritory(string $message)
    {
        return new self(false, $message, true);
    }
}
