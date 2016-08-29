/*
 * TODO:
 *
 *
 * DONE:
 * labels translation: title, text, close
 * allow to change from admin, width and height of the window
 * awesome icon selected by admin, in front of the title
 * beginning scrolling
 * Print directly avoiding the window. In this case the library JQuery UI is not needed.
 *
 */
function onPrint(title, slug, print, close, width, height, icon, print_directly) {
    if(print_directly){
        $("#printContent-"+slug).printThis();
    } else {
        var w = screen.width*width/100;
        var h = screen.height*height/100
        w = w.toFixed();
        h = h.toFixed();
        //console.log(w);
        //console.log(h);
        $("#dialogDiv-"+slug).dialog({
            width: w,
            height: h,
            title: title,
            modal: true,
            closeOnEscape: false,
            buttons: [{
                tabIndex: -1,
                text: print,
                click: function () {
                    //console.log( $( this ).parents(".ui-dialog") );
                    $("#printContent-"+slug).printThis({
                        appendToEl: $( this ).parents( ".ui-dialog" )
                    });
                    //$('#printContent-'+slug).printThis();
                    $(this).dialog("close");
                    $(this).dialog("destroy");
                }
            }, {
                tabIndex: -1,
                text: close,
                click: function () {
                    $(this).dialog("close");
                    $(this).dialog("destroy");
                }
            }],
            open: function (event, ui) {
                $(".ui-dialog-titlebar-close").hide();
                $( ".ui-dialog-title" ).before( "<i class=\"fa fa-"+icon+"\" aria-hidden=\"true\"></i>&nbsp;" );
                $( ".ui-dialog-title" ).css('float','none');
                $( this ).scrollTop(0);
            }
        }, "open");
        event.preventDefault();
    }
}


