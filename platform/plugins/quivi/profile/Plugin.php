<?php namespace Quivi\Profile;

use Illuminate\Contracts\Http\Kernel;
use Quivi\Profile\Classes\CorsMiddleware;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function boot()
    {
        $this->app->make(Kernel::class)->prependMiddleware(CorsMiddleware::class);
    }

    public function registerComponents()
    {
    }

    public function registerSettings()
    {
    }
}
