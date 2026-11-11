<?php

declare(strict_types=1);

namespace Application\Notification\Output;

enum NotificationDelivery
{
    case Delivered;
    case AlreadyDelivered;
}
