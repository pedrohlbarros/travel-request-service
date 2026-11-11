<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Controller;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A bare page that subscribes to the caller's private channel, to watch notifications arrive. Local only.
 */
final readonly class BroadcastPlaygroundController
{
    public function __construct(
        private Application $application,
        private Config $config,
        private Views $views,
    ) {}

    public function __invoke(): View
    {
        if (! $this->application->environment('local')) {
            throw new NotFoundHttpException();
        }

        $reverb = $this->config->get('notification.playground');

        return $this->views->make('notification.playground', ['reverb' => $reverb]);
    }
}
