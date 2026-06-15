<?php namespace Quivi\Vim;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Lang;
use Quivi\Vim\Classes\LocaleMiddleware;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function boot()
    {
        Lang::addNamespace('themes.vim', themes_path('vim/lang'));

        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'appendMiddlewareToGroup')) {
            $kernel->appendMiddlewareToGroup('web', LocaleMiddleware::class);
        }
    }

    public function registerComponents()
    {
    }

    public function registerSettings()
    {
    }
}
