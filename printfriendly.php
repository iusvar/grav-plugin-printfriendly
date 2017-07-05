<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Utils;
use Grav\Plugin\PFManager\PFManager;


/**
 * Class PrintFriendlyPlugin
 * @package Grav\Plugin
 */
class PrintFriendlyPlugin extends Plugin
{
    protected $lang;
    
    protected $route = "pf-manager";
    protected $uri;
    //protected $base;
    //protected $admin_route;
    protected $pfmanager;
    //protected $taskcontroller;
    protected $json_response = [];

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
        require_once __DIR__ . '/classes/pfmanager.php';
        
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
            'onCollectionProcessed' => ['onCollectionProcessed', 0],
            'onTwigInitialized'     => ['onTwigInitialized', 0],
            'onPageInitialized'     => ['onPageInitialized', 1001],
            'onTwigSiteVariables'   => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 0]
        ]);

        $this->pfmanager = new PFManager($this->grav);
        $this->pfmanager->json_response = [];
        $this->grav['pfmanager'] = $this->pfmanager;
    }

/* FOR DEBUG
    public function onCollectionProcessed(Event $event)
    {
        $collection = $event['collection'];
        foreach ($collection as $slug => $page) {
            $route = $page->route();
            //$this->grav['debugger']->addMessage( $route .' - '.end(explode('/', $route)) .' - '. addcslashes($route,'/'));
            $this->grav['debugger']->addMessage( $page );
        }
    }
*/

    /**
     * Add current directory to Twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }


    public function onPageInitialized()
    {
        $post = !empty($_POST) ? $_POST : [];

        $task = !empty($post['pftask']) ? $post['pftask'] : $this->uri->param('pftask');

        if ($task) {
            $this->pfmanager->execute($task, $post);
            if ( $this->pfmanager->json_response ) {
                echo json_encode($this->pfmanager->json_response);
                exit();
            }
        } else {
        }
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

        if ($this->config->get('plugins.pf2.awesome.use_font')) {
            $this->grav['assets']->addCss('plugin://printfriendly/assets/css/font-awesome.min.css');
        }

        $this->grav['assets']
            ->add('jquery', 101)
            ->addJs('plugin://printfriendly/assets/js/printThis.js')
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

        $slug       = end(explode('/', $route));

        $page       = $this->grav['page'];
        $found      = $page->find($route);
        $id         = $found->id();
        $title      = $found->title();
        
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
            
        //if( empty($icn_plugin) && empty($btn_plugin) ){
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


        $html = '
            <div id="dialog-'.$id.'" style="display: none;">
                <div id="print-'.$id.'"></div>
            </div>
            <input id="export-html" type="hidden">
            <button id="' . $btn_id . '" class="export" type="button" data-pf="'.$btn_data.'">' . $btn_link . '</button>
            <script>

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
                                    $("#print-'.$id.'").printThis({
                                        appendToEl: $( this ).parents( ".ui-dialog" )
                                    });
                                    $( "#fa-'.$id.'" ).remove();
                                    $(this).dialog("close");
                                }
                            },
                            {
                                text: "'.$btn_cancel.'",
                                icon: "ui-icon-closethick",
                                click: function() {
                                    $( "#fa-'.$id.'" ).remove();
                                    $( this ).dialog( "close" );
                                }
                            }
                        ],
                        open: function() {
                            //$(".ui-dialog-content").scrollTop(0);

                            if( !close_icon ) {
                                $(".ui-dialog-titlebar-close").hide();
                            }
                            $( ".ui-dialog-title" ).before( "<span id=\"fa-'.$id.'\"><i class=\"fa '.$icn_plugin.'\" aria-hidden=\"true\"></i>&nbsp;</span>" );
                            $( ".ui-dialog-title" ).css("float","none");
                            $( this ).scrollTop(0);
                        }
                    });


                    $("#'.$btn_id.'").on("click", function () {

                        //$("#pf-'.$id.' .fa-print").removeClass(\'fa-print\').addClass(\'fa-spin fa-spinner\');
                        $("#pf-'.$id.' .'.$icn_plugin.'").removeClass(\''.$icn_plugin.'\').addClass(\'fa-spin fa-spinner\');
                        var element = $("button#'.$btn_id.'");
                        var url = element.attr("data-pf");

                        $.ajax({
                            url: url,
                            dataType: "json",
                            method: "get"
                        }).done(function (data) {
                            if (data.status == "success") {

                                var html = ($.trim(data.html)).replace(/\s\s+/g, " ");
                                $("#export-html").val(html);

                                $("#print-'.$id.'").html(html);
                                if( print_directly ) {
                                    $("#print-'.$id.'").printThis({
                                        appendToEl: $( this ).parents( ".ui-dialog" )
                                    });
                                } else {
                                    $("#dialog-'.$id.'").attr("title", "Title");
                                    $("#dialog-'.$id.'").dialog("open");
                                }

                            } else {
                                custom_alert( data.message, data.title );
                            }
                        }).fail(function(jqXHR, textStatus, errorMsg){
                            custom_alert( errorMsg, textStatus );
                        }).always(function(){
                            $("#pf-'.$id.' .fa-spin").removeClass(\'fa-spin fa-spinner\').addClass(\''.$icn_plugin.'\');
                            element.prop( "disabled", false );
                        });
                    });
                });

                function custom_alert( message, title ) {
                    if ( !title ) title = "Alert";
                    if ( !message ) message = "No Message to Display.";
                    $("<div></div>").html( message ).dialog({
                        title: title,
                        resizable: false,
                        modal: true,
                        buttons: {
                            "Ok": function()  {
                                $( this ).dialog( "close" );
                            }
                        }
                    });
                }

            </script>
            ';

        return $html;
    }

}
