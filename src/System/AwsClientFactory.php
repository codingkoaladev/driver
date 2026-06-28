<?php

declare(strict_types=1);

namespace Driver\System;

use Aws\AwsClient;

use function call_user_func;
use function strpos;

class AwsClientFactory
{
    /** @var callable|null */
    private $creator;

    public function __construct(?callable $creator = null)
    {
        $this->creator = $creator;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
    public function create(string $serviceType, array $arguments): AwsClient
    {
        if ($this->creator === null) {
            return $this->doCreate($serviceType, $arguments);
        } else {
            return call_user_func($this->creator, $serviceType, $arguments);
        }
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
    private function doCreate(string $serviceType, array $arguments): AwsClient
    {
        if (strpos($serviceType, "\\") !== false) {
            $type = $serviceType;
        } else {
            $type = '\\Aws\\' . $serviceType . '\\' . $serviceType . 'Client';
        }

        return new $type($arguments);
    }
}
