<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Utils;
use Symfony\Component\Yaml\Yaml;


/**
 * Class PrintFriendlyPlugin
 * @package Grav\Plugin
 */
class PrintFriendlyPlugin extends Plugin
{
    protected $lang;
    
    protected $route = "pf-manager";
    protected $uri;

    protected $listing = array();

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
            'onPluginsInitialized' => [['setting', 1000],['onPluginsInitialized', 0]],
        ];
    }

    public function setting()
    {
        $this->lang = $this->grav['language'];
        $this->uri = $this->grav['uri'];
    }

    /**
     * Activate plugin if path matches to the configured one.
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onTwigInitialized'     => ['onTwigInitialized', 0],
            'onTwigSiteVariables'   => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 0]
        ]);

    }


    /**
     * Add current directory to Twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add `printfriendly()` Twig function
     * Add `pf_implode` Twig filter
     */
    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('printfriendly', [$this, 'generateLink'])
        );

        $this->grav['twig']->twig()->addFilter(
            new \Twig_SimpleFilter('pf_implode', 'implode')
        );
    }

    /**
     * Add CSS and JS to page header
     */
    public function onTwigSiteVariables()
    {
        if ($this->config->get('plugins.printfriendly.built_in_css')) {
            $this->grav['assets']->addCss('plugins://printfriendly/assets/css/printfriendly.css');
        }

        if ($this->config->get('plugins.printfriendly.libraries.jqueryui_version')){
            $version = (string)$this->config->get('plugins.printfriendly.libraries.jqueryui_version');
        } else {
            $version = '1.12.1';
        }

        if ( $this->config->get('plugins.printfriendly.libraries.jqueryui_source') == 'maxcdn' ) {
            $themes_source = 'https://code.jquery.com/ui/'.$version.'/themes/';
            $jquery_ui_source = 'https://code.jquery.com/ui/'.$version.'/';
        } else {
            $themes_source = 'plugin://printfriendly/assets/jquery-ui-themes-'.$version.'/themes/';
            $jquery_ui_source = 'plugin://printfriendly/assets/jquery-ui-'.$version.'/';
        }

        $themes = $this->config->get('plugins.printfriendly.libraries.jqueryui_themes');
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
            ->addJs($jquery_ui_source.'jquery-ui.min.js');
    }

    /**
     * Used by the Twig function to generate HTML
     *
     * @param null $route
     * @param array $options
     * @return string
     */
    public function generateLink($route = null, $options = [])
    {
        if ($route === null) {
            return $this->lang->translate('PLUGIN_PF.ERROR');
        }

        $pf_bur     = $this->grav['base_url_relative'] . DS;
        $pf_manager = $this->route.'.json' . DS;
        $pf_task    = 'pftask:pf' . DS;
        $esc_route  = 'route:'.str_replace('/','@',$route) . DS;
        
        $nonce      = Utils::getNonce('pf-form');
        $pf_nonce   = 'pf-nonce:'.$nonce;

        $page       = $this->grav['page'];
        $found      = $page->find($route);
        $id         = $found->id();
        $title      = $found->title();

        $parameters = [];
        $parameters['breadcrumbs']  = $this->get_crumbs( $found );
        $html_from_template = $this->grav['twig']->processTemplate('printfriendly.html.twig', ['page' => $found, 'parameters' => $parameters]);

        $allow_array = $this->config->get('plugins.printfriendly.tags.allowed_tags');
        $allow = '';
        foreach ($allow_array as $key => $value) {
            if($value){
                $allow .= '<'.$value.'>';
            }
        }
        $stripped_html = strip_tags($html_from_template, $allow);
        
        $html_utf8_decode = utf8_decode($stripped_html);
        $html_base64_encode = base64_encode($html_utf8_decode);

        $btn_id     = "pf-".$id;
        $btn_data   = $pf_bur . $pf_manager . $pf_task . $esc_route . $pf_nonce;

        $print_directly = 'false';
        if( $this->config->get('plugins.printfriendly.basics.print_directly') ){
            $print_directly = 'true';
        }

        $icn_plugin = $this->config->get('plugins.printfriendly.basics.icn_plugin');
        $btn_plugin = $this->config->get('plugins.printfriendly.basics.btn_plugin');
        
        $btn_link = '';
        $btn_link .= ( !empty($icn_plugin) ? '<i class="fa '.$icn_plugin.'" aria-hidden="true"></i>&nbsp;' : '' );
        $btn_link .= ( !empty($btn_plugin) ? $btn_plugin : '' );
        $btn_link = trim($btn_link);
            
        if( empty($btn_link) ){
            $icn_plugin = 'fa-print';
            $btn_link = '<i class="fa '.$icn_plugin.'" aria-hidden="true"></i>&nbsp;Print';
        }

        $w = $this->config->get('plugins.printfriendly.window.width');
        $h = $this->config->get('plugins.printfriendly.window.height');
        if(!$w) $w = 50;
        if(!$h) $h = 40;
        
        $closeonescape = ( $this->config->get('plugins.printfriendly.window.closeonescape') ? 'true' : 'false' );

        $close_icon = ( $this->config->get('plugins.printfriendly.window.close_icon') ? 'true' : 'false' );

        $btn_confirm = $this->config->get('plugins.printfriendly.window.confirm_button');
        $btn_cancel = $this->config->get('plugins.printfriendly.window.cancel_button');
        if( empty($btn_confirm) ) $btn_confirm = 'Print';
        if( empty($btn_cancel) ) $btn_cancel = 'Close';

        $locator = \Grav\Common\Grav::instance()['locator'];
        $blueprints_path = $locator->findResource('plugins://printfriendly/blueprints.yaml');
        $package_yaml = Yaml::parse(file_get_contents($blueprints_path));
        $version = $package_yaml['version'];

        $html = '
            <div id="dialog-'.$id.'" style="display: none;" width="100%">
                <div id="print-'.$id.'"></div>
            </div>
            <input id="hidden-'.$id.'" type="hidden" title="'.($version ? $version : 'Boh').'">
            
            <button id="' . $btn_id . '" class="printfriendly" type="button">' . $btn_link . '</button>
            <script>

                function pfPrint(content){
                    $("<iframe>", { name: "pf-frame", class: "pf-frame" }).appendTo("body").contents().find("body").append(content);
                    window.frames["pf-frame"].focus();
                    window.frames["pf-frame"].print();
                    setTimeout(() => { $(".pf-frame").remove(); }, 1000);
                };

                $(document).ready(function() {

                    var close_icon = '.$close_icon.';
                    var print_directly = '.$print_directly.';
                    var width = '.$w.';
                    var height = '.$h.';
                    var w = screen.width*width/100;
                    var h = screen.height*height/100
                    w = w.toFixed();
                    h = h.toFixed();
                    $( "#dialog-'.$id.'" ).dialog({
                        width: w,
                        height: h,
                        title: "'.$title.'",
                        autoOpen: false,
                        modal: true,
                        closeOnEscape: '.$closeonescape.',
                        buttons: [
                            {
                                text: "'.$btn_confirm.'",
                                icon: "ui-icon-check",
                                click: function() {

                                    var content = $("#print-'.$id.'").html();
                                    pfPrint(content);

                                    $("#print-'.$id.'").empty();
                                    $( "div span#fa-'.$id.'" ).remove();
                                    $(this).dialog("close");
                                }
                            },
                            {
                                text: "'.$btn_cancel.'",
                                icon: "ui-icon-closethick",
                                click: function() {
                                    $("#print-'.$id.'").empty();
                                    $( "div span#fa-'.$id.'" ).remove();
                                    $( this ).dialog( "close" );
                                }
                            }
                        ],
                        open: function() {
                            if( !close_icon ) {
                                $(".ui-dialog-titlebar-close").hide();
                            }
                            $( ".ui-dialog-title" ).before( "<span id=\"fa-'.$id.'\"><i class=\"fa '.$icn_plugin.'\" aria-hidden=\"true\"></i>&nbsp;</span>" );
                            $( ".ui-dialog-title" ).css("float","none");
                            $( this ).scrollTop(0);
                        },
                        close: function() {
                            //$("#print-'.$id.'").delay(100).empty();
                        }
                    });

                    var html_base64_encode = "'.$html_base64_encode.'";
                    $("#hidden-'.$id.'").val(html_base64_encode);

                    $("#'.$btn_id.'").on("click", function () {
                        var encoded = $("#hidden-'.$id.'").val();
                        var decoded = atob(encoded);
                        $("#print-'.$id.'").html(decoded);

                        if( print_directly ) {
                            var content = $("#print-'.$id.'").html();
                            pfPrint(content);
                            $("#print-'.$id.'").empty();
                        } else {
                            $("#dialog-'.$id.'").dialog("open");
                        }
                    });
                    
                });

            </script>
            ';

        return $html;
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
