<?php

namespace Grav\Plugin\PFManager;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\JsonFile;


class PFManager
{
    public $grav;
    public $json_response;
    protected $post;
    protected $task;

    protected $lang;

    public function __construct(Grav $grav)
    {
        $this->grav     = $grav;
        $this->config   = $this->grav['config'];
        $this->lang     = $this->grav['language'];
    }


    public function setMessage($msg, $type = 'info')
    {
        $messages = $this->grav['messages'];
        $messages->add($msg, $type);
    }


    public function messages($type = null)
    {
        $messages = $this->grav['messages'];
        return $messages->fetch($type);
    }


    public function execute($task, $post)
    {
        $this->task = $task;
        $this->post = $post;
        if (!$this->validateNonce()) {
            return false;
        }

        $method = 'task' . ucfirst($this->task);

        if (method_exists($this, $method)) {
            try {
                $success = call_user_func([$this, $method]);
            } catch (\RuntimeException $e) {
                $success = true;
                $this->setMessage($e->getMessage(), 'error');
            }
        }
        return $success;
    }


    protected function validateNonce()
    {
        if (method_exists('Grav\Common\Utils', 'getNonce')) {
            if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
                if (isset($this->post['pf-nonce'])) {
                    $nonce = $this->post['pf-nonce'];
                } else {
                    $nonce = $this->grav['uri']->param('pf-nonce');
                }

                if (!$nonce || !Utils::verifyNonce($nonce, 'pf-form')) {
                    if ($this->task == 'addmedia') {

                        $message = sprintf($this->lang->translate('PLUGIN_ADMIN.FILE_TOO_LARGE', null),
                            ini_get('post_max_size'));

                        $this->json_response = [
                            'status'  => 'error',
                            'message' => $message
                        ];

                        return false;
                    }

                    $this->setMessage($this->lang->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN'), 'error');
                    $this->json_response = [
                        'status'  => 'error',
                        'title' => $this->lang->translate('PLUGIN_PF.INVALID_SECURITY_TOKEN_TITLE'),
                        'message' => $this->lang->translate('PLUGIN_PF.INVALID_SECURITY_TOKEN_TEXT')
                    ];

                    return false;
                }
                unset($this->post['pf-nonce']);
            } else {
                $nonce = $this->grav['uri']->param('pf-nonce');
                if (!isset($nonce) || !Utils::verifyNonce($nonce, 'pf-form')) {
                    $this->setMessage($this->lang->translate('PLUGIN_PF.INVALID_SECURITY_TOKEN'), 'error');
                    $this->json_response = [
                        'status'  => 'error',
                        'title' => $this->lang->translate('PLUGIN_PF.INVALID_SECURITY_TOKEN_TITLE'),
                        'message' => $this->lang->translate('PLUGIN_PF.INVALID_SECURITY_TOKEN_TEXT')
                    ];
                    return false;
                }
            }
        }
        return true;
    }


    protected function taskPf()
    {
        $route    = $this->grav['uri']->param('route') ;
        $route = str_replace('@','/',$route);
        $slug = end(explode('/', $route));

        try {
            $ret_value  = $this->makeDocument($route);
            $filename   = $ret_value['slug'];
            $html       = $ret_value['html'];
        } catch (\Exception $e) {
            $this->json_response = [
                'status'    => 'error',
                'title'     => 'Error 1',
                'message'   => $this->lang->translate('PLUGIN_PF.AN_ERROR_OCCURRED') . '. ' . $e->getMessage()
            ];
            return true;
        }

        $btn_plugin = $this->lang->translate('PLUGIN_PF.BTN_PLUGIN');
        $message    = $this->lang->translate('PLUGIN_PF.YOUR_DOCUMENT_IS_READY_FOR');

        $this->json_response = [
            'status'    => 'success',
            'message'   => $message,
            'slug'      => $slug,
            'html'      => $html
        ];

        return true;
    }


    protected function makeDocument($route)
    {
        $page = $this->grav['page'];
        $found = $page->find( $route);

        $slug = end(explode('/', $route));

        $parameters = [];
        $html = '';
        
        $parameters['breadcrumbs']  = $this->get_crumbs( $found );
        
        $template_file = 'printfriendly.html.twig';
        $twig = $this->grav['twig'];
        $html = $twig->processTemplate($template_file, ['page' => $found, 'parameters' => $parameters]);

        $allow_array = $this->config->get('plugins.printfriendly.tags.allowed_tags');
        $allow = '';
        foreach ($allow_array as $key => $value) {
            if($value){
                $allow .= '<'.$value.'>';
            }
        }
        $html = strip_tags($html, $allow);

        $ret_value = array();
        $ret_value['slug'] = $slug;
        $ret_value['html'] = $html;
        return $ret_value;
    }


    protected function get_crumbs( $page )
    {
        $current = $page;
        $hierarchy = array();
        while ($current && !$current->root()) {
            $hierarchy[$current->url()] = $current;
            $current = $current->parent();
        }
        $home = $this->grav['pages']->dispatch('/');
        if ($home && !array_key_exists($home->url(), $hierarchy)) {
            $hierarchy[] = $home;
        }
        $elements = array_reverse($hierarchy);
        $crumbs = array();
        foreach ($elements as $key => $crumb) {
            $crumbs[] = [ 'route' => $crumb->route(), 'title' => $crumb->title() ];
        }

        return $crumbs;
    }

}
