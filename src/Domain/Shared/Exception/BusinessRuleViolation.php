<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

use DomainException;

abstract class BusinessRuleViolation extends DomainException {}
