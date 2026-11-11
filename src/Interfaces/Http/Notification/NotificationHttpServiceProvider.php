<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification;

use Application\Notification\Exception\NotificationNotFound;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Interfaces\Http\Shared\Auth\AuthenticatedUser;
use Interfaces\Http\Shared\Problem\ProblemType;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;

final class NotificationHttpServiceProvider extends ServiceProvider
{
    public const string ID_PARAMETER = 'notificationId';

    public const string USER_CHANNEL = 'users.{userId}';

    public function boot(ProblemTypeRegistry $problemTypes, BroadcastManager $broadcasting): void
    {
        $problemTypes->register(
            exceptionClass: NotificationNotFound::class,
            problemType: new ProblemType(
                status: 404,
                type: '/problems/not-found',
                title: 'The resource was not found.',
                isDetailFromException: false,
            ),
        );

        $broadcasting->routes(['middleware' => ['api', 'auth']]);
        $broadcaster = $broadcasting->connection();
        \assert($broadcaster instanceof Broadcaster);
        $broadcaster->channel(
            self::USER_CHANNEL,
            static fn(AuthenticatedUser $user, string $userId): bool => $user->id === $userId,
        );

        Route::middleware('api')
            ->group(base_path('routes/notification.php'));
    }
}
