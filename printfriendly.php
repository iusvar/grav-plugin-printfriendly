<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\File;


/**
 * Class PrintFriendlyPlugin
 * @package Grav\Plugin
 */
class PrintFriendlyPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized'  => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 0],
            'onTwigInitialized'     => ['onTwigInitialized', 0],
            'onTwigSiteVariables'   => ['onTwigSiteVariables', 0],
        ]);
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add simple `printfriendly()` Twig function
     */
    public function onTwigInitialized() {
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('printfriendly', [$this, 'generateLink'])
        );
    }

    /**
     * Add CSS and JS to page header
     */
    public function onTwigSiteVariables() {
        if ($this->config->get('plugins.printfriendly.built_in_css')) {
            $this->grav['assets']->addCss('plugins://printfriendly/assets/css/printfriendly.css');
        }

        if ($this->config->get('plugins.printfriendly.jqueryui.version')){
            $version = (string)$this->config->get('plugins.printfriendly.jqueryui.version');
        } else {
            $version = '1.12.0';
        }
        if ( $this->config->get('plugins.printfriendly.jqueryui.source') == 'maxcdn' ) {
            $themes_source = 'https://code.jquery.com/ui/'.$version.'/themes/';
            $jquery_ui_source = 'https://code.jquery.com/ui/'.$version.'/';
        } else {
            $themes_source = 'plugin://printfriendly/assets/jquery-ui-themes-'.$version.'/themes/';
            $jquery_ui_source = 'plugin://printfriendly/assets/jquery-ui-'.$version.'/';
        }
        
        $themes = $this->config->get('plugins.printfriendly.jqueryui.themes');
        if($themes){
            $this->grav['assets']->addCss($themes_source.$themes.'/jquery-ui.css');
        } else {
            $this->grav['assets']->addCss($themes_source.'smoothness/jquery-ui.css');
        }
        
        if ($this->config->get('plugins.printfriendly.awesome.use_font')) {
            $this->grav['assets']->addCss('plugin://printfriendly/assets/css/font-awesome.min.css');
        }

        $this->grav['assets']
            ->add('jquery', 101)
            ->addJs('plugin://printfriendly/assets/js/printThis.js')
            ->addJs('plugin://printfriendly/assets/js/printfriendly.js')
            ->addJs($jquery_ui_source.'jquery-ui.min.js');
    }

    /**
     * Used by the Twig function to generate HTML
     *
     * @param null $route
     * @param array $options
     * @return string
     */
    public function generateLink($route=null, $options = []) {
        if ($route === null) {
            return '<i>'.$this->grav['language']->translate('PRINTFRIENDLY_PLUGIN.ERROR').'</i>';
        }

        $uri = $this->grav['uri'];
        $page = $this->grav['page'];
        $found = $page->find($route);

        $print_directly = $this->config->get('plugins.printfriendly.print_directly');
        $width = $this->config->get('plugins.printfriendly.window.width');
        $height = $this->config->get('plugins.printfriendly.window.height');
        $icon = $this->config->get('plugins.printfriendly.awesome.icon');

        $template_file = 'printfriendly.html.twig';
        $template_vars = [
            'found' => $found,
            'width' => $width,
            'height' => $height,
            'icon' => $icon,
            'print_directly' => $print_directly
        ];
        $html = $this->grav['twig']->processTemplate($template_file, $template_vars);

        $allow_array = $this->config->get('plugins.printfriendly.tags.allowed_tags');
        $allow = '';
        foreach ($allow_array as $key => $value) {
            if($value){
                $allow .= '<'.$value.'>';
            }
        }
        $html = strip_tags($html, $allow);

        return $html;
    }

}
