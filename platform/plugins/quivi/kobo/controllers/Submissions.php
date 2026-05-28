<?php namespace Quivi\Kobo\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Submissions extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Quivi.Kobo', 'main-menu-item', 'side-menu-item');
    }
}
